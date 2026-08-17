<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 *
 * @package   theme_lambda
 * @copyright 2023 redPIthemes
 *
 */
global $USER, $CFG;

$fa_user_icon = "fa fa-user"; if ($PAGE->theme->settings->use_fa5 == 1) {$fa_user_icon = "fas fa-user";}
$fa_user_icon_alt = "fa fa-user-o"; if ($PAGE->theme->settings->use_fa5 == 1) {$fa_user_icon_alt = "far fa-user";}
$fa_pass_icon = "fa fa-key"; if ($PAGE->theme->settings->use_fa5 == 1) {$fa_pass_icon = "fas fa-unlock-alt";}
?>
<div class="container-fluid" style="margin-top:-12px;">      
	<div class="row-fluid">    	

		<div class="span12 login-header">
			<div class="profileblock centered-logo">

				<?php			
				$wwwroot = '';
				if (empty($CFG->loginhttps)) {
					$wwwroot = $CFG->wwwroot;
				} else {
					$wwwroot = str_replace("http://", "https://", $CFG->wwwroot);
				}

				if (!isloggedin() or isguestuser()) { ?>
				<div id="top-login">
					
							<a class="dropdown-toggle plus" href="<?php echo $wwwroot . '/login/index.php' ?>"><i class="<?php echo $fa_user_icon_alt; ?>" aria-hidden="true"></i> <?php echo get_string('login') ?></a>
							
				</div>

				<?php } else { ?>
				<div id="loggedin-user">		
					<?php echo $OUTPUT->navbar_plugin_output();
					echo $OUTPUT->user_menu();
					echo $OUTPUT->user_picture($USER, array('size' => 50, 'class' => 'welcome_userpicture')); ?>	
				</div>
				<?php } ?>
				
			</div>
		</div>
	</div>
</div>

<div class="container-fluid">    
	<div class="row-fluid">

		<?php if (!$haslogo) { ?>
		<div class="span8">
			<div class="title-text">
				<h1 id="title"><?php echo $SITE->fullname; ?></h1>
			</div>
		</div>
		<?php } else { ?>
		<div class="span12">
			<div class="logo-header">
				<a class="logo" href="<?php echo $CFG->wwwroot; ?>" title="<?php print_string('home'); ?>">
					<?php 
					echo html_writer::empty_tag('img', array('src'=>$PAGE->theme->setting_file_url('logo', 'logo'), 'class'=>'img-responsive', 'alt'=>'logo', 'style'=>'margin: 0 auto;display: block;'));
					?>
				</a>

			</div>
		</div>
		<?php } ?> 

	</div>
</div>
