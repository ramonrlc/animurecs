<?php
  if (str_replace("\\", "/", __FILE__) === $_SERVER['SCRIPT_FILENAME']) {
    echo "This partial cannot be rendered by itself.";
    exit;
  }
  $firstAnime = Anime::first($this);
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name='description' content="Animurecs is an anime-centric social network that gives you personalized recommendations by learning your tastes. Discover new anime you'll love today!" />
    <meta name='keywords' content='anime, recommendations, anime list, recommend, top anime' />

    <title><?php echo (isset($params['title']) ? escape_output($params['title']) : "Animurecs").(isset($params['subtitle']) && $params['subtitle'] != '' ? ' - '.escape_output($params['subtitle']) : ' - Social Anime Recommendations'); ?></title>
    <link href='/favicon.ico' rel='shortcut icon' />

    <link href='//ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css' rel='stylesheet' />
    <link href='<?php echo Config::ROOT_URL; ?>/css/jquery.dataTables.css' rel='stylesheet' />
    <link href='<?php echo Config::ROOT_URL; ?>/css/token-input.css' rel='stylesheet' />
    <link href='<?php echo Config::ROOT_URL; ?>/css/animurecs.css?v=0.1' rel='stylesheet' />

    <script src='//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js' type='text/javascript'></script>
    <script src='//ajax.googleapis.com/ajax/libs/jqueryui/1.9.1/jquery-ui.min.js' type='text/javascript'></script>

    <!--<script type='text/javascript' src='<?php echo Config::ROOT_URL; ?>/jquery-ui-timepicker-addon.min.js'></script>-->
    <script src='<?php echo Config::ROOT_URL; ?>/js/jquery.dropdownPlain.min.js' type='text/javascript'></script>
    <script src='//cdnjs.cloudflare.com/ajax/libs/datatables/1.9.4/jquery.dataTables.min.js' type='text/javascript'></script>
    <script src='<?php echo Config::ROOT_URL; ?>/js/jquery.tokeninput.min.js' type='text/javascript'></script>
    <script src='<?php echo Config::ROOT_URL; ?>/js/jquery.json-2.3.min.js' type='text/javascript'></script>
    <script src='<?php echo Config::ROOT_URL; ?>/js/jqplot/jquery.jqplot.min.js' type='text/javascript'></script>
    <script src='<?php echo Config::ROOT_URL; ?>/js/jqplot/jqplot.barRenderer.min.js' type='text/javascript'></script>
    <script src='<?php echo Config::ROOT_URL; ?>/js/jqplot/jqplot.categoryAxisRenderer.min.js' type='text/javascript'></script>
    <script src='<?php echo Config::ROOT_URL; ?>/js/jqplot/jqplot.dateAxisRenderer.min.js' type='text/javascript'></script>
    <script src='<?php echo Config::ROOT_URL; ?>/js/jqplot/jqplot.pieRenderer.min.js' type='text/javascript'></script>
    <script src='<?php echo Config::ROOT_URL; ?>/js/jqplot/jqplot.highlighter.min.js' type='text/javascript'></script>
    <script src='<?php echo Config::ROOT_URL; ?>/js/jqplot/jqplot.trendline.min.js' type='text/javascript'></script>

    <script src='//www.google.com/jsapi' type='text/javascript'></script>
    <script src='//cdnjs.cloudflare.com/ajax/libs/d3/3.0.1/d3.v3.min.js' type='text/javascript'></script>
    <script src='<?php echo Config::ROOT_URL; ?>/js/d3-helpers.js' type='text/javascript'></script>

    <script src='//cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/2.3.1/js/bootstrap.min.js' type='text/javascript'></script>
    <script src='<?php echo Config::ROOT_URL; ?>/js/animurecs.js' type='text/javascript'></script>
    <script type="text/javascript">
      var _gaq = _gaq || [];
      _gaq.push(['_setAccount', 'UA-37523517-1']);
      var pluginUrl = '//www.google-analytics.com/plugins/ga/inpage_linkid.js';
      _gaq.push(['_require', 'inpage_linkid', pluginUrl]);
      _gaq.push(['_trackPageview']);
      (function() {
        var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
        ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
        var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
      })();
    </script>
  </head>
  <body>
    <div class='navbar navbar-inverse navbar-fixed-top'>
      <div class='navbar-inner'>
        <div class='container-fluid'>
          <a href='/' class='brand'>Animurecs</a>
<?php
  if ($this->user->loggedIn()) {
?>
          <ul class='nav'>
            <li class='divider-vertical'></li>
            <li><?php echo $this->user->link("globalFeed", "<i class='icon-th-list icon-white'></i> Feed", Null, True); ?></li>
            <li class='divider-vertical'></li>
            <li><?php echo $this->user->link("show", "<i class='icon-home icon-white'></i> You", Null, True); ?></li>
            <li class='divider-vertical'></li>
            <li><a href='/users/'><i class='icon-globe icon-white'></i> Connect</a></li>
            <li class='divider-vertical'></li>
            <li><?php echo $this->user->link("discover", "<i class='icon-star icon-white'></i> Discover", Null, True); ?></li>
            <li class='divider-vertical'></li>
          </ul>
<?php
  }
?>
          <ul class='nav pull-right'>
<?php
  if ($this->user->loggedIn()) {
?>
            <?php echo $firstAnime->view('searchForm', array(
              'form' => array(
                  'class' => 'navbar-search'
                ),
              'searchInput' => array(
                  'id' => 'navbar-anime-search',
                  'class' => 'autocomplete search-query'
                ),
              'submitButton' => False
            )); ?>
            <li id='navbar-alerts'>
<?php
    if ($this->user->outstandingFriendRequests) {
?>
              <span class='dropdown'><a class='dropdown-toggle' data-toggle='dropdown' href='#'><span class='badge badge-info'><?php echo count($this->user->outstandingFriendRequests); ?></span></a>
              <?php echo $this->user->view('friendRequestList', $params); ?>
              </span>
<?php
    }
?>
            </li>
            <li id='navbar-user' class='dropdown'>
              <a href='#' class='dropdown-toggle' data-toggle='dropdown'><i class='icon-user icon-white'></i><?php echo escape_output($this->user->username); ?><b class='caret'></b></a>
              <ul class='dropdown-menu'>
                <li><?php echo $this->user->link("show", "Profile"); ?></li>
                <li><?php echo $this->user->link("edit", "Settings"); ?></li>
<?php
    if ($this->user->isAdmin() && !isset($this->user->switchedUser)) {
?>
                <li><?php echo $this->user->link("switch_user", "Switch User"); ?></li>
<?php
    }
    if (isset($this->user->switchedUser) && is_numeric($this->user->switchedUser)) {
?>
                <li><?php echo $this->user->link("switch_back", "Switch Back"); ?></li>
<?php
    }
?>
                <li><a href='/logout.php'>Sign out</a></li>
              </ul>
<?php
  } else {
?>
                <li>
                  <?php echo $this->user->view('login'); ?>
                </li>
<?php
  }
?>
          </ul>
        </div>
      </div>
    </div>
    <div class='container-fluid'>
<?php
  if ($this->status != '') {
?>
      <div class='alert alert-<?php echo isset($this->class) ? escape_output($this->class) : ""; ?>'>
    <button class='close' data-dismiss='alert' href='#'>×</button>
  <?php echo escape_output($this->status); ?></div>
<?php
  }
?>