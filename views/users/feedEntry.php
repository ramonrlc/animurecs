<?php
  require_once($_SERVER['DOCUMENT_ROOT']."/global/includes.php");
  $this->app->check_partial_include(__FILE__);
  // outputs feed markup for the feed entry provided at params['entry'].
  // also takes an optional parameter at params['nested'] to indicate if entry is nested.
  $nowTime = new DateTime("now", $this->app->outputTimeZone);

  $diffInterval = $nowTime->diff($params['entry']->time());

  $feedMessage = $params['entry']->formatFeedEntry();

  $blankEntryComment = new Comment($this->app, 0, $this->app->user, $params['entry']);

  $entryType = isset($params['nested']) && $params['nested'] ? "div" : "li";

  ?>
        <<?php echo $entryType; ?> class='media'>
      <div class='pull-right feedDate' data-time='<?php echo $params['entry']->time()->format('U'); ?>'><?php echo ago($diffInterval); ?></div>
      <?php echo $params['entry']->user->link("show", $params['entry']->user->avatarImage(array('class' => 'feedAvatarImg')), Null, True, array('class' => 'feedAvatar pull-left')); ?>
      <div class='media-body feedText'>
        <div class='feedEntry'>
          <h4 class='media-heading feedUser'><?php echo $feedMessage['title']; ?></h4>
          <?php echo $feedMessage['text']; ?>
<?php
  if ($params['entry']->allow($this->app->user, 'delete')) {
?>
          <ul class='feedEntryMenu hidden'><li><?php echo $params['entry']->link("delete", "<i class='icon-trash'></i> Delete", Null, True); ?></li></ul>
<?php
  }
?>
        </div>
<?php
  if ($params['entry']->comments) {
    $commentGroup = new EntryGroup($this->app, $params['entry']->comments);
    foreach ($commentGroup->load('info')->load('users')->load('comments')->entries() as $commentEntry) {
      echo $this->view('feedEntry', array('entry' => $commentEntry, 'nested' => True));
    }
  }
  if ($params['entry']->allow($this->app->user, 'comment') && $blankEntryComment->depth() < 2) {
?>
        <div class='entryComment'><?php echo $blankEntryComment->view('inlineForm', array('currentObject' => $params['entry'])); ?></div>
<?php
  }
?>
      </div>
    </<?php echo $entryType; ?>>