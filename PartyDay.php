<?php

/**
 * @name         PartyDay
 * @main         PartyDay\PartyDay
 * @author       OneiricDay
 * @version      Master - Beta 1
 * @api          3.0.0
 * @description (!) 파티 시스템
 */


namespace PartyDay;


use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;

use pocketmine\Player;
use pocketmine\Server;

use pocketmine\utils\Config;
use pocketmine\form\Form as OriginalForm;

use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;



class PartyDay extends PluginBase implements Listener
{


	/** @var string */
	public static $prefix = '§e§l PartyDay > §r§f';
	
	/** @var bool */
	public static $saveData = false;


	const SUCCESS = 1;
	const EXIST_SAME_NAME = 0;
	const ALREADY_HAVE_PARTY = -1;
	const INAPPROPRIATE_NAME = -2;

	const MINIMUM_LENGTH = 2;
	const MAXIMUM_LENGTH = 16;
	
	const MAXIMUM_MEMBERS_COUNT = 8;



    /**
     * @param string|Player $player
     * @return string
     */

	public static function convertName ($player) : string
	{
		
		return $player instanceof Player ? strtolower ($player->getName()) : strtolower ($player);
		
	}


    /**
     * @param string|Player $player
     * @return string|null
     */

	public static function getParty ($player) : ?string
	{

		$name = self::convertName ($player);
		return self::$playerData [$name] ?? null;

	}


    /**
     * @param string|Player $player
     * @return bool
     */
	 
	public static function hasParty ($player) : bool
	{
		
		return ! is_null (self::getParty ($player));
		
	}

	
    /**
     * @param string|Player $player
	 * $param string $party
     * @return bool
     */
	 
	public static function setParty ($player, string $party) : bool
	{

		if (! self::isExistParty ($party))
			return false;

		if (

			self::hasParty ($player) &&
			! self::quitParty ($player)

		) return false;

		$name = self::convertName ($player);

		self::$partyData [$party]['멤버'][$name] = true;
		self::$playerData [$name] = $party;

		return true;

	}
	
	
    /**
     * @param string|Player $player
	 * @param string|null $message
	 * $param bool $force
     * @return bool
     */
	 
	public static function quitParty ($player, ?string $message = null, bool $force = false) : bool
	{

		if (! self::hasParty ($player))
			return false;

		$party = self::getParty ($player);
		$name = self::convertName ($player);

		if (! $force && self::getPartyMaster ($party) === $name)
			return false;

		unset (self::$partyData [$party]['멤버'][$name]);
		unset (self::$playerData [$name]);

		if (! is_null ($message))
		{

			self::msgToParty ($party, $message);

		}

		return true;

	}


    /**
	 * $param string $party
     * @return bool
     */

	public static function isExistParty (string $party) : bool
	{

		return isset (self::$partyData [$party]);

	}
	

    /**
	 * $param string $party
     * @return string
     */

	public static function getPartyMaster (string $party) : string
	{

		if (! self::isExistParty ($party))
			return '';

		return self::$partyData [$party]['파티장'];

	}
	
	
    /**
	 * $param string $party
     * @return array
     */

	public static function getPartyMembers (string $party) : array
	{

		if (! self::isExistParty ($party))
			return [];

		return array_keys (self::$partyData [$party]['멤버']);

	}
	
	
    /**
	 * $param string $party
     * @return array
     */

	public static function getPartyOnlineMembers (string $party) : array
	{

		$members = self::getPartyMembers ($party);

		if (count ($members) < 1)
			return [];
		
		$online = [];

		foreach ($members as $k => $v)
			if (($player = Server::getInstance()->getPlayerExact ($v)) !== null)
				$online[] = $player;

		return $online;

	}
	
	
    /**
	 * $param string $party
	 * $param string $message
     */

	public static function msgToParty (string $party, string $message)
	{

		foreach (self::getPartyOnlineMembers ($party) as $k => $v)
		{

			$v->sendMessage (self::$prefix . $message);

		}

	}


    /**
	 * $param string $party
	 * @param string|Player $master
     * @return int
     */

	public static function createParty (string $party, $master) : int
	{

		foreach (self::$partyData as $partys => $partyDatas)
			if (strtolower ($party) === strtolower ($partys))
				return self::EXIST_SAME_NAME;

		if (self::hasParty ($master))
			return self::ALREADY_HAVE_PARTY;

		if (($length = mb_strlen ($party, 'utf-8')) < self::MINIMUM_LENGTH || $length > self::MAXIMUM_LENGTH)
			return self::INAPPROPRIATE_NAME;

		$name = self::convertName ($master);

		self::$partyData [$party] = [

			'파티장' => $name,
			'멤버' => []

		];

		self::setParty ($master, $party);
		return self::SUCCESS;
		
	}


    /**
	 * $param string $party
	 * $param string|null $message
	 * $param string|null $broadcastMessage
     * @return bool
     */

	public static function removeParty (string $party, ?string $message = null, ?string $broadcastMessage = null) : bool
	{

		if (! self::isExistParty ($party))
			return false;

		if (! is_null ($message))
			self::msgToParty ($party, $message);

		if (! is_null ($broadcastMessage))
			Server::getInstance()->broadcastMessage (self::$prefix . $broadcastMessage);

		foreach (self::getPartyMembers ($party) as $k => $v)
			self::quitParty ($v, null, true);

		unset (self::$partyData [$party]);
		return true;

	}


	/** @var array */
	public static $playerData = [];
	
	/** @var array */
	public static $partyData = [];
	
	/** @var Config */
	public static $config = null;


	public function onEnable () : void
	{

		if (self::$saveData)
		{

			self::$config = new Config ($this->getDataFolder() . 'partyData.yml', Config::YAML, []);
			self::$partyData = self::$config->getAll();

			foreach (self::$partyData as $k => $v)
				foreach (self::getPartyMembers ($k) as $k2 => $v2)
					self::$playerData [$v2] = $k;

		}

		$this->getLogger()->info (count (self::$partyData) . '개의 파티가 로딩되었습니다.');
		$this->getServer()->getPluginManager()->registerEvents ($this, $this);

        $cmd = new PluginCommand ('파티', $this);
        $cmd->setDescription ('자신의 파티를 만들고 관리해보세요');

        Server::getInstance()->getCommandMap()->register ('파티', $cmd);

	}


	public function onDisable () : void
	{

		if (self::$saveData)
		{

			self::$config->setAll (self::$partyData);
			self::$config->save ();

		}

		$this->getLogger()->info (count (self::$partyData) . "개의 파티가 저장되었습니다.");

	}
	
	
	public function onJoin (PlayerJoinEvent $event)
	{

		$player = $event->getPlayer();

		if (! self::hasParty ($player))
			return;

		self::msgToParty (self::getParty ($player), '파티원 §e' . $player->getName() . '§f님께서 서버에 접속하셨습니다.');

	}
	
	
	public function onQuit (PlayerQuitEvent $event)
	{

		$player = $event->getPlayer();

		if (! self::hasParty ($player))
			return;

		self::msgToParty (self::getParty ($player), '파티원 §e' . $player->getName() . '§f님께서 서버에서 퇴장하셨습니다.');

	}
	
	
	public function onCommand (CommandSender $player, Command $command, string $label, array $args) : bool
	{
		
		$text = "\n      - 자신의 파티를 만들고 관리해보세요! -";
		
		if (self::hasParty ($player))
		{

			$members = [];

			foreach (self::getPartyMembers (self::getParty ($player)) as $k => $v)
				$members[] = (Server::getInstance()->getPlayerExact ($v) === null ? '§c' : '§a') . $v;

			$text .= "\n      파티 멤버: " . implode ('§f, ', $members) . "\n";

		}

		$text .= "\n      §7- 제작자: OneiricDay -\n\n";

		$form = new Form ();
		$form->setTitle ('§l* 파티 시스템 | OneiricDay');
		$form->setContent ($text);

		$form->addButton ('파티 생성하기');
		$form->addButton ('파티 탈퇴하기');
		$form->addButton ('파티 해산하기');
		$form->addButton ('파티 초대하기');

		$form->setFunction (function ($player, $data)
		{
			
			if ($data === 0)
			{
			
				$form = new CustomForm ();
				$form->setTitle ('§l* 파티 시스템 | 파티 생성하기');

				$form->addLabel ("\n§f- 파티 이름 조건 -\n" . self::MINIMUM_LENGTH . "글자 ~ " . self::MAXIMUM_LENGTH . "글자\n");
				$form->addInput ('생성할 파티 이름', '예) 아모르파티');
				
				$form->setFunction (function ($player, $data)
				{
					
					if (is_null (($party = $data[1])))
					{
						
						$player->sendMessage (self::$prefix . '생성할 이름을 입력해주세요.');
						return;
						
					}

					$result = self::createParty ($party, $player);

					if ($result === self::SUCCESS)
					{

						$player->sendMessage (self::$prefix . '파티를 성공적으로 생성했습니다. 이제 다른 멤버를 모집해보세요.');
						return;

					}

					if ($result === self::EXIST_SAME_NAME)
					{

						$player->sendMessage (self::$prefix . '동일한 이름의 파티가 이미 존재합니다.');
						return;

					}

					if ($result === self::ALREADY_HAVE_PARTY)
					{

						$player->sendMessage (self::$prefix . '당신은 이미 파티를 운영중이거나 파티에 소속되어 있습니다.');
						return;

					}
					
					if ($result === self::INAPPROPRIATE_NAME)
					{

						$player->sendMessage (self::$prefix . '부적합한 이름입니다.');
						return;

					}

				});
				
				$form->sendForm ($player);

				return;

			}

			if ($data === 1)
			{

				if (! self::quitParty ($player, $player->getName() . '님이 파티에서 탈퇴했습니다.'))
					$player->sendMessage (self::$prefix . '당신은 소속된 파티가 없거나 파티장입니다.');

				return;

			}
			
			if ($data === 2)
			{

				if (! self::hasParty ($player))
				{

					$player->sendMessage (self::$prefix . '당신은 소속된 파티가 없습니다.');
					return;

				}

				if (! self::getPartyMaster (self::getParty ($player)) === self::convertName ($player))
				{

					$player->sendMessage (self::$prefix . '파티 해산은 파티장만 사용할 수 있습니다.');
					return;

				}

				self::removeParty (self::getParty ($player), '소속된 파티가 해산되었습니다.');
				return;

			}
			
			if ($data === 3)
			{
				
				if (! self::hasParty ($player))
				{

					$player->sendMessage (self::$prefix . '당신은 소속된 파티가 없습니다.');
					return;

				}
				
				$players = Server::getInstance()->getOnlinePlayers();
				
				foreach ($players as $k => $v)
					if (self::hasParty ($v))
						unset ($players [$k]);

				$form = new Form ();
				$form->setTitle ('§l* 파티 시스템 | 파티 초대하기');
				$form->setContent ("\n      - 자신의 파티에 초대해보세요! -\n\n");
				
				foreach ($players as $k => $v)
					$form->addButton ($v->getName());
					
				$party = self::getParty ($player);

				$form->setFunction (function ($player, $data) use ($players, $party)
				{
					
					if (is_null ($data))
					{
						
						$player->sendMessage (self::$prefix . '초대할 유저를 선택해주세요.');
						return;
						
					}

					if (! ($selectedPlayer = array_values ($players) [$data])->isOnline())
					{
					
						$player->sendMessage (self::$prefix . '해당 유저는 서버에서 퇴장했습니다.');
						return;
						
					}
					
					$player->sendMessage (self::$prefix . '해당 유저에게 가입 요청을 보냈습니다.');
					
					$form = new ModalForm ();
					$form->setTitle ('§l* 파티 시스템 | 가입 요청');
					$form->setContent ("\n- {$party} 파티에서 당신을 초대합니다. -\n\n소속 인원: " . implode (", ", self::getPartyMembers ($party)) . "\n\n");
					
					$form->setButton1 ('수락하기');
					$form->setButton2 ('거절하기');
					
					$form->setFunction (function ($player, $data) use ($party)
					{
						
						if (! self::isExistParty ($party))
						{

							$player->sendMessage (self::$prefix . '파티가 해산되었습니다.');
							return;

						}

						if (self::hasParty ($player))
						{

							$player->sendMessage (self::$prefix . '다른 파티에 소속되어 있습니다.');
							self::msgToParty ($party, '다른 파티에 소속되어 ' . $player->getName() . '님에게 보낸 가입 신청이 거절되었습니다.');

							return;

						}

						if ($data !== true)
						{

							$player->sendMessage (self::$prefix . '요청을 거절했습니다.');
							self::msgToParty ($party, '' . $player->getName() . '님에게 보낸 가입 요청이 거절되었습니다.');
							return;
							
						}

						if (count (self::getPartyMembers ($party)) + 1 > self::MAXIMUM_MEMBERS_COUNT)
						{

							$player->sendMessage (self::$prefix . '인원이 꽉 찼습니다.');
							self::msgToParty ($party, '파티 인원이 꽉 차서 ' . $player->getName() . '님에게 보낸 가입 요청이 거절되었습니다.');

							return;

						}

						self::setParty ($player, $party);
						self::msgToParty ($party, '파티에 ' . $player->getName() . '님이 들어오셨습니다! §e(' . count (self::getPartyMembers ($party)) . '/' . self::MAXIMUM_MEMBERS_COUNT . ')');

					});

					$form->sendForm ($selectedPlayer);
					
				});
				
				$form->sendForm ($player);
				return;
				
			}
			
		});
		
		$form->sendForm ($player);
		return true;
		
	}


}

class ModalForm implements OriginalForm
{


	protected $data = [

		'type' => 'modal',
		'title' => '',
		'content' => '',
		'button1' => '',
		'button2' => ''

	];

	protected $call = null;


	public function setTitle (string $title)
	{

		$this->data ['title'] = $title;

	}

	public function setContent (string $content)
	{

		$this->data ['content'] = $content;

	}

	public function setButton1 (string $button)
	{

		$this->data ['button1'] = $button;

	}
	
	public function setButton2 (string $button)
	{

		$this->data ['button2'] = $button;

	}

	public function setFunction (?callable $call)
	{

		$this->call = $call;

	}

	public function handleResponse (Player $player, $data) : void
	{

		$callable = $this->call;

		if (is_null ($callable))
			return;

		$callable ($player, $data);

	}

	public function jsonSerialize () : array
	{

		return $this->data;

	}

	public function sendForm (Player $player)
	{

		$player->sendForm ($this);

	}

}

class Form implements OriginalForm
{


	protected $data = [

		'type' => 'form',
		'title' => '',
		'content' => '',
		'buttons' => []

	];

	protected $call = null;


	public function setTitle (string $title)
	{

		$this->data ['title'] = $title;

	}

	public function setContent (string $content)
	{

		$this->data ['content'] = $content;

	}

	public function addButton (string $button)
	{

		$this->data ['buttons'][] = ['text' => $button];

	}

	public function setFunction (?callable $call)
	{

		$this->call = $call;

	}

	public function handleResponse (Player $player, $data) : void
	{

		$callable = $this->call;

		if (is_null ($callable))
			return;

		$callable ($player, $data);

	}

	public function jsonSerialize () : array
	{

		return $this->data;

	}

	public function sendForm (Player $player)
	{

		$player->sendForm ($this);

	}

}

class CustomForm implements OriginalForm
{


	protected $data = [

		'type' => 'custom_form',
		'title' => '',
		'content' => []

	];

	protected $call = null;


	public function setTitle (string $title)
	{

		$this->data ['title'] = $title;

	}

	public function addInput (string $text, ?string $placeholder = null, ?string $default = null)
	{

		$data = ['type' => 'input', 'text' => $text];

		if (! is_null ($placeholder))
			$data ['placeholder'] = $placeholder;

		if (! is_null ($default))
			$data ['default'] = $default;

		$this->data ['content'][] = $data;

	}
	
	public function addLabel (string $text)
	{

		$this->data ['content'][] = ['type' => 'label', 'text' => $text];

	}

	public function setFunction (?callable $call)
	{

		$this->call = $call;

	}

	public function handleResponse (Player $player, $data) : void
	{

		$callable = $this->call;

		if (is_null ($callable))
			return;

		$callable ($player, $data);

	}

	public function jsonSerialize () : array
	{

		return $this->data;

	}

	public function sendForm (Player $player)
	{

		$player->sendForm ($this);

	}

}