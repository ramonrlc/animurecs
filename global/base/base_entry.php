<?php

abstract class BaseEntry extends BaseObject {
  // feed entry class object.
  use Commentable;

  protected $user, $userId;
  protected $time;
  protected $status, $score;

  // this is the parent object to which the entry belongs.
  // e.g. for an animelist entry on a user's page, it'd be the animelist
  // and for a comment entry on the user's page, it'd be the comment
  public $object;

  public function __construct(Application $app, $id=Null, $params=Null) {
    parent::__construct($app, $id);
    if ($id === 0) {
      $this->time = new DateTime("now", $this->app->serverTimeZone);
      $this->status = $this->score = 0;
      $this->comments = [];
    } else {
      $this->user = $this->userId = $this->time = $this->status = $this->score = $this->comments = Null;
    }
    if (is_array($params)) {
      foreach ($params as $key=>$value) {
        $this->{$this->humanizeParameter($key)} = $value;
      }
    }
  }
  public function userId() {
    return $this->returnInfo('userId');
  }
  public function user() {
    if ($this->user === Null) {
      $this->user = new User($this->app, $this->userId());
    }
    return $this->user;
  }
  public function time() {
    return new DateTime($this->returnInfo('time'), $this->app->serverTimeZone);
  }
  public function status() {
    return $this->returnInfo('status');
  }
  public function score() {
    return $this->returnInfo('score');
  }
  public function allow(User $authingUser, $action, array $params=Null) {
    // takes a user object and an action and returns a bool.
    switch($action) {
      case 'new':
      case 'edit':
      case 'delete':
        if ($authingUser->id == $this->userId() || $authingUser->isStaff()) {
          return True;
        }
        return False;
        break;
      case 'index':
        if ($authingUser->isAdmin()) {
          return True;
        }
        return False;
        break;
      case 'comment':
        if ($authingUser->loggedIn()) {
          return True;
        }
        return False;
        break;
      case 'show':
        return True;
        break;
      default:
        return False;
        break;
    }
  }
  public function create_or_update(array $entry, array $whereConditions=Null) {
    /*
      Creates or updates an existing list entry for the current user.
      Takes an array of entry parameters.
      Returns the resultant list entry ID.
    */
    // ensure that this user and list type exist.
    try {
      $user = new User($this->app, intval($entry['user_id']));
      $user->getInfo();
      $type = new $this->entryType($this->app, intval($entry[$this->typeID]));
      $type->getInfo();
    } catch (Exception $e) {
      return False;
    }
    if (!parent::validate($entry)) {
      return False;
    }

    $params = [];
    foreach ($entry as $parameter => $value) {
      if (!is_array($value)) {
        if (is_numeric($value)) {
            $params[] = "`".$this->dbConn->real_escape_string($parameter)."` = ".intval($value);
        } else {
          $params[] = "`".$this->dbConn->real_escape_string($parameter)."` = ".$this->dbConn->quoteSmart($value);
        }
      }
    }

    // check to see if this is an update.
    $entryGroup = $this->entries();
    if (isset($entryGroup->entries()[intval($entry['id'])])) {
      $this->beforeUpdate($entry);
      $updateDependency = $this->dbConn->stdQuery("UPDATE `".static::$modelTable."` SET ".implode(", ", $params)." WHERE `id` = ".intval($entry['id'])." LIMIT 1");
      if (!$updateDependency) {
        return False;
      }
      $returnValue = intval($entry['id']);
      $this->afterUpdate();
    } else {
      if (!isset($entry['time'])) {
        $params[] = "`time` = NOW()";
      }
      $this->beforeCreate($entry);
      $insertDependency = $this->dbConn->stdQuery("INSERT INTO `".static::$modelTable."` SET ".implode(",", $params));
      if (!$insertDependency) {
        return False;
      }
      $returnValue = intval($this->dbConn->insert_id);
      $this->afterCreate($entry);
    }
    return $returnValue;
  }

  // all feed entry classes must implement a way to format said feed entries into markup.
  abstract public function formatFeedEntry();
}

?>