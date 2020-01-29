    <div class="navbar-wrapper">

        <div class="navbar navbar-default navbar-static-top" role="navigation">
          <div class="container-fluid">
            <div class="navbar-header">
              <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
              </button>
             
            <div class="navbar-collapse collapse">
              <ul class="nav navbar-nav">
                <?php if ( $use_questions ) { ?>
                
                <li style="float: left; height: 50px; line-height: 20px;">
                <a href="index.php<?php if ($default_action != 'change') { echo '?action=change'; } ?>">
                <i class="fa fa-fw fa-cog"></i> <?php echo $messages["title"]; ?></a>
              </li>
                <?php } ?>
                <?php if ( $change_sshkey ) { ?>
                <li class="<?php if ( $action === "changesshkey" ) { echo "active"; } ?>">
                  <a href="?action=changesshkey"
                     data-toggle="menu-popover"
                     data-content="<?php echo htmlentities(strip_tags($messages["changehelpsshkey"])); ?>"
                  ><i class="fa fa-fw fa-terminal"></i> <?php echo $messages["menusshkey"]; ?></a>
                </li>

                <?php } ?>
              </ul>
            </div>
          </div>
        </div>

    </div>
