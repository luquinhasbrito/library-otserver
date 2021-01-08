<?php

namespace Otserver;

class Account extends ObjectData
{

    const LOADTYPE_ID = 'id';
    const LOADTYPE_NAME = 'name';
    const LOADTYPE_MAIL = 'email';

    public static $table = 'accounts';
    public $data = array('name' => null, 'password' => null, 'premdays' => 0, 'lastday' => 0, 'email' => null, 'key' => null, 'blocked' => 0, 'warnings' => 0, 'group_id' => 1, 'page_lastday' => 1, 'email_new' => null, 'email_new_time' => 0, 'created' => 0, 'rlname' => null, 'location' => null, 'page_access' => 0, 'email_code' => null, 'next_email' => 0, 'premium_points' => 0, 'last_post' => 0, 'flag' => 0, 'created_ip' => null);
    public static $fields = array('id',  'name',  'password', 'premdays', 'lastday', 'email', 'key', 'blocked', 'warnings', 'group_id', 'page_lastday', 'email_new', 'email_new_time', 'created', 'rlname', 'location', 'page_access', 'email_code', 'next_email', 'premium_points', 'last_post', 'flag', 'created_ip');
    public $players;
    public $playerRanks;
    public $guildAccess;
    public $bans;
    public $historyPacc;
    public $transactions;

    public function __construct($search_text = null, $search_by = self::LOADTYPE_ID)
    {
        if ($search_text != null)
            $this->load($search_text, $search_by);
    }

    public function load($search_text, $search_by = self::LOADTYPE_ID)
    {
        if (in_array($search_by, self::$fields))
            $search_string = $this->getDatabaseHandler()->fieldName($search_by) . ' = ' . $this->getDatabaseHandler()->quote($search_text);
        else
            new Error_Critic('', 'Wrong Account search_by type.');
        $fieldsArray = array();
        foreach (self::$fields as $fieldName)
            $fieldsArray[$fieldName] = $this->getDatabaseHandler()->fieldName($fieldName);
        $this->data = $this->getDatabaseHandler()->query('SELECT ' . implode(', ', $fieldsArray) . ' FROM ' . $this->getDatabaseHandler()->tableName(self::$table) . ' WHERE ' . $search_string)->fetch();
    }

    public function loadById($id)
    {
        $this->load($id, 'id');
    }

    public function loadByName($name)
    {
        $this->load($name, 'name');
    }

    public function loadByEmail($mail)
    {
        $this->load($mail, 'email');
    }

    public function save($forceInsert = false)
    {
        if (!isset($this->data['id']) || $forceInsert) {
            $keys = array();
            $values = array();
            foreach (self::$fields as $key)
                if ($key != 'id') {
                    $keys[] = $this->getDatabaseHandler()->fieldName($key);
                    $values[] = $this->getDatabaseHandler()->quote($this->data[$key]);
                }
            $this->getDatabaseHandler()->query('INSERT INTO ' . $this->getDatabaseHandler()->tableName(self::$table) . ' (' . implode(', ', $keys) . ') VALUES (' . implode(', ', $values) . ')');
            $this->setID($this->getDatabaseHandler()->lastInsertId());
        } else {
            $updates = array();
            foreach (self::$fields as $key)
                if ($key != 'id')
                    $updates[] = $this->getDatabaseHandler()->fieldName($key) . ' = ' . $this->getDatabaseHandler()->quote($this->data[$key]);
            $this->getDatabaseHandler()->query('UPDATE ' . $this->getDatabaseHandler()->tableName(self::$table) . ' SET ' . implode(', ', $updates) . ' WHERE ' . $this->getDatabaseHandler()->fieldName('id') . ' = ' . $this->getDatabaseHandler()->quote($this->data['id']));
        }
    }

    public function getPlayers($forceReload = false)
    {
        if (!isset($this->players) || $forceReload) {
            $this->players = new DatabaseList('Player');
            $this->players->setFilter(new SQL_Filter(new SQL_Field('account_id'), SQL_Filter::EQUAL, $this->getID()));
            $this->players->addOrder(new SQL_Order(new SQL_Field('name')));
        }
        return $this->players;
    }

    public function getGuildRanks($forceReload = false)
    {
        if (!isset($this->playerRanks) || $forceReload) {
            $this->playerRanks = new DatabaseList('AccountGuildRank');
            $filterAccount = new SQL_Filter(new SQL_Field('account_id', 'players'), SQL_Filter::EQUAL, $this->getID());
            $filterPlayer = new SQL_Filter(new SQL_Field('rank_id', 'players'), SQL_Filter::EQUAL, new SQL_Field('id', 'guild_ranks'));
            $filterGuild = new SQL_Filter(new SQL_Field('guild_id', 'guild_ranks'), SQL_Filter::EQUAL, new SQL_Field('id', 'guilds'));
            $filter = new SQL_Filter($filterAccount, SQL_Filter::CRITERIUM_AND, $filterPlayer);
            $filter = new SQL_Filter($filter, SQL_Filter::CRITERIUM_AND, $filterGuild);
            $this->playerRanks->setFilter($filter);
        }
        return $this->playerRanks;
    }

    public function loadGuildAccess($forceReload = false)
    {
        if (!isset($this->guildAccess) || $forceReload) {
            $this->guildAccess = array();
            foreach ($this->getGuildRanks($forceReload) as $rank)
                if ($rank->getOwnerID() == $rank->getPlayerID())
                    $this->guildAccess[$rank->getGuildID()] = Guild::LEVEL_OWNER;
                elseif (!isset($this->guildAccess[$rank->getGuildID()]) || $rank->getLevel() > $this->guildAccess[$rank->getGuildID()])
                    $this->guildAccess[$rank->getGuildID()] = $rank->getLevel();
        }
    }

    public function isInGuild($guildId, $forceReload = false)
    {
        $this->loadGuildAccess($forceReload);
        return isset($this->guildAccess[$guildId]);
    }

    public function getGuildLevel($guildId, $forceReload = false)
    {
        $this->loadGuildAccess($forceReload);
        if (isset($this->guildAccess[$guildId]))
            return $this->guildAccess[$guildId];
        else
            return 0;
    }

    public function unban()
    {
        $bans = new DatabaseList('Ban');
        $filterType = new SQL_Filter(new SQL_Field('type'), SQL_Filter::EQUAL, Ban::TYPE_ACCOUNT);
        $filterValue = new SQL_Filter(new SQL_Field('value'), SQL_Filter::EQUAL, $this->data['id']);
        $filterActive = new SQL_Filter(new SQL_Field('active'), SQL_Filter::EQUAL, 1);
        $filter = new SQL_Filter($filterType, SQL_Filter::CRITERIUM_AND, $filterValue);
        $filter = new SQL_Filter($filter, SQL_Filter::CRITERIUM_AND, $filterActive);
        $bans->setFilter($filter);
        foreach ($bans as $ban) {
            $ban->setActive(0);
            $ban->save();
        }
    }

    public function loadBans($forceReload = false)
    {
        if (!isset($this->bans) || $forceReload) {
            $this->bans = new DatabaseList('Ban');
            $filterType = new SQL_Filter(new SQL_Field('type'), SQL_Filter::EQUAL, Ban::TYPE_ACCOUNT);
            $filterValue = new SQL_Filter(new SQL_Field('value'), SQL_Filter::EQUAL, $this->data['id']);
            $filterActive = new SQL_Filter(new SQL_Field('active'), SQL_Filter::EQUAL, 1);
            $filter = new SQL_Filter($filterType, SQL_Filter::CRITERIUM_AND, $filterValue);
            $filter = new SQL_Filter($filter, SQL_Filter::CRITERIUM_AND, $filterActive);
            $this->bans->setFilter($filter);
        }
    }

    public function isBanned($forceReload = false)
    {
        $this->loadBans($forceReload);
        $isBanned = false;
        foreach ($this->bans as $ban) {
            if ($ban->getExpires() <= 0 || $ban->getExpires() > time())
                $isBanned = true;
        }
        return $isBanned;
    }

    public function getBanTime($forceReload = false)
    {
        $this->loadBans($forceReload);
        $lastExpires = 0;
        foreach ($bans as $ban) {
            if ($ban->getExpires() <= 0) {
                $lastExpires = 0;
                break;
            }
            if ($ban->getExpires() > time() && $ban->getExpires() > $lastExpires)
                $lastExpires = $ban->getExpires();
        }
        return $lastExpires;
    }

    public function getHistoryPacc($forceReload = false)
    {
        if (!isset($this->historyPacc) || $forceReload) {
            $this->historyPacc = new DatabaseList();
            $this->historyPacc->setClass('HistoryPacc');
            $this->historyPacc->setFilter(new SQL_Filter(new SQL_Field('from_account'), SQL_Filter::EQUAL, $this->getID()));
        }
        return $this->historyPacc;
    }

    public function getTransactions($forceReload = false)
    {
        if (!isset($this->transactions) || $forceReload) {
            $this->transactions = new DatabaseList();
            $this->transactions->setClass('Transactions');
            $this->transactions->setFilter(new SQL_Filter(new SQL_Field('account_id'), SQL_Filter::EQUAL, $this->getID()));
        }
        return $this->transactions;
    }

    public function delete()
    {
        $this->getDatabaseHandler()->query('DELETE FROM ' . $this->getDatabaseHandler()->tableName(self::$table) . ' WHERE ' . $this->getDatabaseHandler()->fieldName('id') . ' = ' . $this->getDatabaseHandler()->quote($this->data['id']));

        unset($this->data['id']);
    }

    public function setID($value)
    {
        $this->data['id'] = $value;
    }

    public function getID()
    {
        return $this->data['id'];
    }

    public function setName($value)
    {
        $this->data['name'] = $value;
    }

    public function getName()
    {
        return $this->data['name'];
    }

    public function setPassword($value)
    {
        $this->data['password'] = Website::encryptPassword($value, $this);
    }

    public function getPassword()
    {
        return $this->data['password'];
    }

    public function setPremDays($value)
    {
        $this->data['premdays'] = $value;
    }

    public function getPremDays()
    {
        return $this->data['premdays'] - (date("z", time()) + (365 * (date("Y", time()) - date("Y", $this->data['lastday']))) - date("z", $this->data['lastday']));
    }

    public function setLastDay($value)
    {
        $this->data['lastday'] = $value;
    }

    public function getLastDay()
    {
        return $this->data['lastday'];
    }

    public function setMail($value)
    {
        $this->data['email'] = $value;
    }

    public function getMail()
    {
        return $this->data['email'];
    }

    public function setKey($value)
    {
        $this->data['key'] = $value;
    }

    public function getKey()
    {
        return $this->data['key'];
    }

    public function setGroupID($value)
    {
        $this->data['group_id'] = $value;
    }

    public function getGroupID()
    {
        return $this->data['group_id'];
    }

    public function setCreateDate()
    {
        return $this->data['created'];
    }

    public function getCreateIP()
    {
        return $this->data['created_ip'];
    }

    public function setCreateIP()
    {
        return $this->data['created_ip'];
    }

    /*
     * Custom AAC fields
     * premium_points , INT, default 0
     * page_access, INT, default 0
     * location, VARCHAR(255), default ''
     * rlname, VARCHAR(255), default ''
     */

    public function getCreateDate()
    {
        return $this->data['created'];
    }

    public function setPremiumPoints($value)
    {
        $this->data['premium_points'] = $value;
    }

    public function getPremiumPoints()
    {
        return $this->data['premium_points'];
    }

    public function setPageAccess($value)
    {
        $this->data['page_access'] = $value;
    }

    public function getPageAccess()
    {
        return $this->data['page_access'];
    }

    public function setLocation($value)
    {
        $this->data['location'] = $value;
    }

    public function getLocation()
    {
        return $this->data['location'];
    }

    public function setRLName($value)
    {
        $this->data['rlname'] = $value;
    }

    public function getRLName()
    {
        return $this->data['rlname'];
    }

    public function setFlag($value)
    {
        $this->data['flag'] = $value;
    }

    public function getFlag()
    {
        return $this->data['flag'];
    }

    public function setRecoveryKey($value)
    {
        $this->data['key'] = $value;
    }

    public function getRecoveryKey()
    {
        return $this->data['key'];
    }

    /*
     * for compability with old scripts
     */

    public function getGroup()
    {
        return $this->getGroupID();
    }

    public function setGroup($value)
    {
        $this->setGroupID($value);
    }

    public function getEMail()
    {
        return $this->getMail();
    }

    public function setEMail($value)
    {
        $this->setMail($value);
    }

    public function getPlayersList()
    {
        return $this->getPlayers();
    }

    public function getGuildAccess($guildID)
    {
        return $this->getGuildLevel($guildID);
    }

    public function isValidPassword($password)
    {
        return ($this->data['password'] == Website::encryptPassword($password, $this));
    }

    public function find($name)
    {
        $this->loadByName($name);
    }

    public function findByEmail($email)
    {
        $this->loadByEmail($email);
    }

    public function isPremium()
    {
        return ($this->getPremDays() > 0);
    }

    public function getLastLogin()
    {
        return $this->getLastDay();
    }

    public function isBlocked()
    {
        return $this->data['blocked'] ? 1 : 0;
    }

    public function unblock()
    {
        $this->data['blocked'] = 0;
    }

    public function block()
    {
        $this->data['blocked'] = 1;
    }
}
