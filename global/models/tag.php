<?php
class Tag extends BaseObject {
  public static $modelTable = "tags";
  public static $modelPlural = "tags";

  protected $name;
  protected $description;
  protected $type;

  protected $createdUser;

  protected $numAnime;
  protected $anime;

  protected $numManga;
  protected $manga;

  public function __construct(Application $app, $id=Null, $name=Null) {
    if ($name !== Null) {
      $id = intval($app->dbConn->queryFirstValue("SELECT `id` FROM `tags` WHERE `name` = ".$app->dbConn->quoteSmart(str_replace("_", " ", $name))." LIMIT 1"));
    }
    parent::__construct($app, $id);
    if ($id === 0) {
      $this->name = "New Tag";
      $this->description = "";
      $this->type = $this->anime = $this->manga = $this->createdUser = [];
    } else {
      $this->name = $this->description = $this->type = $this->anime = $this->manga = $this->createdUser = Null;
    }
  }
  public function name() {
    return $this->returnInfo('name');
  }
  public function description() {
    return $this->returnInfo('description');
  }
  public function createdAt() {
    return new DateTime($this->returnInfo('createdAt'), $this->app->serverTimeZone);
  }
  public function updatedAt() {
    return new DateTime($this->returnInfo('updatedAt'), $this->app->serverTimeZone);
  }
  public function allow(User $authingUser, $action, array $params=Null) {
    // takes a user object and an action and returns a bool.
    switch($action) {
      // case 'approve':
      case 'new':
      case 'edit':
      case 'delete':
        if ($authingUser->isStaff()) {
          return True;
        }
        return False;
        break;
      case 'token_search':
        if ($authingUser->loggedIn()) {
          return True;
        }
        return False;
        break;
      case 'show':
      case 'index':
        return True;
        break;
      default:
        return False;
        break;
    }
  }
  public function create_or_update_tagging($anime_id, User $currentUser) {
    /*
      Creates or updates an existing tagging for the current anime.
      Takes a tag ID.
      Returns a boolean.
    */
    // check to see if this is an update.
    $this->anime();
    if (isset($this->anime()[intval($anime_id)])) {
      return True;
    }
    try {
      $anime = new Anime($this->app, intval($anime_id));
      $anime->getInfo();
    } catch (Exception $e) {
      return False;
    }
    $this->beforeUpdate(array());
    $anime->beforeUpdate(array());
    $insertTagging = $this->dbConn->stdQuery("INSERT INTO `anime_tags` (`anime_id`, `tag_id`, `created_user_id`, `created_at`) VALUES (".intval($anime->id).", ".intval($this->id).", ".intval($currentUser->id).", NOW())");
    if (!$insertTagging) {
      return False;
    }
    $this->afterUpdate(array());
    $anime->afterUpdate(array());
    $this->anime[intval($anime->id)] = array('id' => intval($anime->id), 'title' => $anime->title);
    return True;
  }
  public function drop_taggings(array $animus=Null) {
    /*
      Deletes tagging relations.
      Takes an array of anime ids as input, defaulting to all anime.
      Returns a boolean.
    */
    $this->anime();
    if ($animus === Null) {
      $animus = array_keys($this->anime()->anime());
    }
    $animeIDs = [];
    foreach ($animus as $anime) {
      if (is_numeric($anime)) {
        $animeIDs[] = intval($anime);
      }
    }
    if ($animeIDs) {
      $animeObjects = [];
      foreach($animeIDs as $animeID) {
        $animeObjects[$animeID] = new Anime($this->app, $animeID);
        $animeObjects[$animeID]->beforeUpdate(array());
      }
      $this->beforeUpdate(array());
      $drop_taggings = $this->dbConn->stdQuery("DELETE FROM `anime_tags` WHERE `tag_id` = ".intval($this->id)." AND `anime_id` IN (".implode(",", $animeIDs).") LIMIT ".count($animeIDs));
      if (!$drop_taggings) {
        return False;
      }
      $this->afterUpdate(array());
      foreach ($animeObjects as $anime) {
        $anime->afterUpdate(array());
      }
    }
    foreach ($animeIDs as $animeID) {
      unset($this->anime[intval($animeID)]);
    }
    return True;
  }
  public function validate(array $tag) {
    if (!parent::validate($tag)) {
      return False;
    }
    if (!isset($tag['name']) || strlen($tag['name']) < 1 || strlen($tag['name']) > 50) {
      return False;
    }
    if (isset($tag['description']) && (strlen($tag['description']) < 0 || strlen($tag['description']) > 600)) {
      return False;
    }
    if (!isset($tag['created_user_id'])) {
      return False;
    }
    if (!is_numeric($tag['created_user_id']) || intval($tag['created_user_id']) != $tag['created_user_id'] || intval($tag['created_user_id']) <= 0) {
      return False;
    } else {
      try {
        $createdUser = new User($this->app, intval($tag['created_user_id']));
        $createdUser->getInfo();
      } catch (Exception $e) {
        return False;
      }
    }
    if (!isset($tag['tag_type_id'])) {
      return False;
    }
    if (!is_numeric($tag['tag_type_id']) || intval($tag['tag_type_id']) != $tag['tag_type_id'] || intval($tag['tag_type_id']) <= 0) {
      return False;
    } else {
      try {
        $parent = new TagType($this->app, intval($tag['tag_type_id']));
        $parent->getInfo();
      } catch (Exception $e) {
        return False;
      }
    }
    return True;
  }
  public function create_or_update(array $tag, array $whereConditions=Null) {
    // creates or updates a tag based on the parameters passed in $tag and this object's attributes.
    // returns False if failure, or the ID of the tag if success.
    // make sure this tag name adheres to standards.
    $tag['name'] = str_replace("_", " ", strtolower($tag['name']));

    // filter some parameters out first and replace them with their corresponding db fields.
    if (isset($tag['anime_tags']) && !is_array($tag['anime_tags'])) {
      $tag['anime_tags'] = explode(",", $tag['anime_tags']);
    }

    //go ahead and create or update this tag.
    if (!parent::create_or_update($tag)) {
      return False;
    }

    // now process any taggings.
    if (isset($tag['anime_tags'])) {
      // drop any unneeded access rules.
      $animeToDrop = array();
      foreach ($this->anime() as $anime) {
        if (!in_array($anime->id, $tag['anime_tags'])) {
          $animeToDrop[] = intval($anime->id);
        }
      }
      $drop_anime = $this->drop_taggings($animeToDrop);
      foreach ($tag['anime_tags'] as $animeToAdd) {
        if (!array_filter_by_property($this->anime()->anime(), 'id', $animeToAdd)) {
          // find this tagID.
          $animeID = intval($this->dbConn->queryFirstValue("SELECT `id` FROM `anime` WHERE `id` = ".intval($animeToAdd)." LIMIT 1"));
          if ($animeID) {
            $create_tagging = $this->create_or_update_tagging($animeID, $currentUser);
          }
        }
      }
    }
    return $this->id;
  }
  public function delete($entries=Null) {
    // delete this tag from the database.
    // returns a boolean.

    // drop all taggings for this tag first.
    $dropTaggings = $this->drop_taggings();
    if (!$dropTaggings) {
      return False;
    }
    return parent::delete();
  }
  public function isApproved() {
    // Returns a bool reflecting whether or not the current anime is approved.
    // doesn't do anything for now. maybe use later.
    /* 
    if ($this->approvedOn() === '' or !$this->approvedOn()) {
      return False;
    }
    return True;
    */
  }
  public function getApprovedUser() {
    // retrieves an id,name array corresponding to the user who approved this anime.
    // return $this->dbConn->queryFirstRow("SELECT `users`.`id`, `users`.`name` FROM `anime` LEFT OUTER JOIN `users` ON `users`.`id` = `anime`.`approved_user_id` WHERE `anime`.`id` = ".intval($this->id));
  }
  public function getCreatedUser() {
    // retrieves a user object corresponding to the user who created this tag.
    return new User($this->app, intval($this->dbConn->queryFirstValue("SELECT `created_user_id` FROM `tags` WHERE `id` = ".intval($this->id))));
  }
  public function createdUser() {
    if ($this->createdUser === Null) {
      $this->createdUser = $this->getCreatedUser();
    }
    return $this->createdUser;
  }
  public function getType() {
    // retrieves the tag type that this tag belongs to.
    return new TagType($this->app, intval($this->dbConn->queryFirstValue("SELECT `tag_type_id` FROM `tags` WHERE `id` = ".intval($this->id))));
  }
  public function type() {
    if ($this->type === Null) {
      $this->type = $this->getType();
    }
    return $this->type;
  }
  public function getAnime() {
    // retrieves a list of anime objects corresponding to anime tagged with this tag.
    $animes = [];
    $animeIDs = $this->dbConn->stdQuery("SELECT `anime_id` FROM `anime_tags` WHERE `tag_id` = ".intval($this->id));
    while ($animeID = $animeIDs->fetch_assoc()) {
      $animes[intval($animeID['anime_id'])] = new Anime($this->app, intval($animeID['anime_id']));
    }
    return new AnimeGroup($this->app, $animes);
  }
  public function anime() {
    if ($this->anime === Null) {
      $this->anime = $this->getAnime();
    }
    return $this->anime;
  }
  public function getNumAnime() {
    // retrieves the number of anime tagged with this tag.
    return $this->dbConn->queryCount("SELECT COUNT(*) FROM `anime_tags` WHERE `tag_id` = ".intval($this->id));
  }
  public function numAnime() {
    if ($this->numAnime === Null) {
      $this->numAnime = $this->getNumAnime();
    }
    return $this->numAnime;
  }
  public function render() {
    if (isset($_POST['tag']) && is_array($_POST['tag'])) {
      $updateTag = $this->create_or_update($_POST['tag']);
      if ($updateTag) {
        $this->app->redirect($this->url("show"), array('status' => "Successfully updated.", 'class' => 'success'));
      } else {
        $this->app->redirect(($this->id === 0 ? $this->url("new") : $this->url("edit")), array('status' => "An error occurred while creating or updating this tag.", 'class' => 'error'));
      }
    }
    switch($this->app->action) {
      case 'token_search':
        $tags = [];
        if (isset($_REQUEST['term'])) {
          $tags = $this->dbConn->queryAssoc("SELECT `id`, `name` FROM `tags` WHERE MATCH(`name`) AGAINST(".$this->dbConn->quoteSmart($_REQUEST['term'])." IN BOOLEAN MODE) ORDER BY `name` ASC;");
        }
        echo json_encode($tags);
        exit;
        break;
      case 'new':
        $title = "Create a Tag";
        $output = $this->view('new');
        break;
      case 'edit':
        if ($this->id == 0) {
          $this->app->display_error(404);
        }
        $title = "Editing ".escape_output($this->name());
        $output = $this->view('edit');
        break;
      case 'show':
        if ($this->id == 0) {
          $this->app->display_error(404);
        }
        $title = "Tag: ".escape_output($this->name());
        $output = $this->view('show', array('recsEngine' => $this->app->recsEngine));
        break;
      case 'delete':
        if ($this->id == 0) {
          $this->app->display_error(404);
        }
        if (!$this->app->checkCSRF()) {
          $this->app->display(403);
        }
        $tagName = $this->name();
        $deleteTag = $this->delete();
        if ($deleteTag) {
          $this->app->redirect('/tags/', array('status' => 'Successfully deleted '.$tagName.'.', 'class' => 'success'));
        } else {
          $this->app->redirect($this->url("show"), array('status' => 'An error occurred while deleting '.$tagName.'.', 'class' => 'error'));
        }
        break;
      default:
      case 'index':
        $title = "All Tags";
        $output = $this->view('index');
        break;
    }
    return $this->app->render($output, array('subtitle' => $title));
  }
  public function url($action="show", $format=Null, array $params=Null, $name=Null) {
    // returns the url that maps to this object and the given action.
    if ($name === Null) {
      $name = $this->name();
    }
    $urlParams = "";
    if (is_array($params)) {
      $urlParams = http_build_query($params);
    }
    return "/".escape_output(self::modelUrl())."/".($action !== "index" ? rawurlencode(rawurlencode($name))."/".escape_output($action)."/" : "").($format !== Null ? ".".escape_output($format) : "").($params !== Null ? "?".$urlParams : "");
  }
  public function link($action="show", $text="Show", $format=Null, $raw=False, array $params=Null, array $urlParams=Null, $id=Null) {
    // returns an HTML link to the current object's profile, with text provided.
    $linkParams = [];
    if (!is_array($params)) {
      $params = array();
    }
    if (!isset($params['title'])) {
      $params['title'] = $this->name();
    }
    if (!isset($params['class'])) {
      $params['class'] = 'tag-'.$this->type()->name;
    } else {
      $params['class'] .= ' tag-'.$this->type()->name;
    }
    foreach ($params as $key => $value) {
      $linkParams[] = escape_output($key)."='".escape_output($value)."'";
    }
    return "<a href='".$this->url($action, $format, $urlParams, $id)."' ".implode(" ", $linkParams).">".($raw ? $text : escape_output($text))."</a>";
  }
}
?>