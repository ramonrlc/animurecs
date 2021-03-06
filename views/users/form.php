<?php
  require_once($_SERVER['DOCUMENT_ROOT']."/global/includes.php");
  $this->app->check_partial_include(__FILE__);
?>
    <?php echo $this->app->form(['action' => ($this->id === 0) ? $this->url("new") : $this->url("edit"), 'enctype' => 'multipart/form-data', 'class' => 'form-horizontal']); ?>
      <?php echo ($this->id === 0) ? "" : "<input type='hidden' name='user[id]' value='".intval($this->id)."' />"; ?>
      <fieldset>
        <div class='control-group'>
          <label class='control-label' for='user[name]'>Name</label>
          <div class='controls'>
            <?php echo $this->input('name', ['type' => 'text', 'class' => 'input-xlarge']); ?>
          </div>
        </div>
<?php
  if ($this->id === 0) {
?>        <div class='control-group'>
          <label class='control-label' for='user[username]'>Username</label>
          <div class='controls'>
            <?php echo $this->input('username', ['type' => 'text', 'class' => 'input-xlarge']); ?>
          </div>
        </div>
<?php
  } else {
?>
            <?php echo $this->input('username', ['type' => 'hidden']); ?>
<?php
  }
?>        <div class='control-group'>
          <label class='control-label' for='user[password]'>Password</label>
          <div class='controls'>
            <?php echo $this->input('password', ['type' => 'password', 'class' => 'input-xlarge']); ?>
          </div>
        </div>
        <div class='control-group'>
          <label class='control-label' for='user[password_confirmation]'>Confirm Password</label>
          <div class='controls'>
            <?php echo $this->input('password_confirmation', ['type' => 'password', 'class' => 'input-xlarge']); ?>
          </div>
        </div>
        <div class='control-group'>
          <label class='control-label' for='user[email]'>Email</label>
          <div class='controls'>
            <?php echo $this->input('email', ['type' => 'email', 'class' => 'input-xlarge']); ?>
          </div>
        </div>
        <div class='control-group'>
          <label class='control-label' for='user[about]'>About</label>
          <div class='controls'>
            <textarea name='user[about]' id='user[about]' rows='5'><?php echo ($this->id === 0) ? "" : escape_output($this->about()); ?></textarea>
          </div>
        </div>
<?php
  if ($this->id != 0) {
?>        <div class='control-group'>
          <label class='control-label' for='avatar_image'>Avatar</label>
          <div class='controls'>
            <?php echo $this->input('avatar_image', ['type' => 'file', 'class' => 'input-file', 'onChange' => 'displayImagePreview(this.files);']); ?><span class='help-inline'>Max size 300x300, JPEG/PNG/GIF.</span>
          </div>
        </div>
<?php
  }
  if ($this->allow($this->app->user, $this->id === 0 ? 'new' : 'edit') && $this->app->user->isStaff()) {
?>      <div class='control-group'>
          <label class='control-label' for='user[usermask]'>Role(s)</label>
          <div class='controls'>
            <?php echo $this->view('roleCheckboxes'); ?>
          </div>
        </div>
<?php
  } else {
?>      <input type='hidden' name='user[usermask][]' value='<?php echo $this->id === 0 ? 1 : intval($this->usermask()); ?>' />
<?php
        }
?>      <div class='form-actions'>
          <button type='submit' class='btn btn-primary'><?php echo ($this->id === 0) ? "Sign Up" : "Save changes"; ?></button>
          <a href='#' onClick='window.location.replace(document.referrer);' class='btn'><?php echo ($this->id === 0) ? "Go back" : "Discard changes"; ?></a>
        </div>
      </fieldset>
    </form>