<?php

class theme_th_lambda_core_renderer extends theme_lambda_core_renderer {
	 
	protected function render_custom_menu(custom_menu $menu) {
		global $CFG;

		$hasdisplaymycourses = theme_lambda_get_setting('mycourses_dropdown');

		if (isloggedin() && !isguestuser() && $hasdisplaymycourses) {

			$branchlabel = get_string('mycourses');
			$branchurl = new moodle_url('');
			$branchtitle = $branchlabel;
			$branchsort = 10000;
			$branch = $menu->add($branchlabel, $branchurl, $branchtitle, $branchsort);

			// if (!$sortorder = $CFG->navsortmycoursessort) {
			// 	$sortorder = 'sortorder';
			// }
			$sortorder = 'startdate';
			$courses_limit = $CFG->navcourselimit;
			// if ($mycourses = enrol_get_my_courses(NULL, 'visible DESC, ' . $sortorder . ' ASC', $courses_limit)) {
			// if ($mycourses = enrol_get_my_courses(NULL, 'visible DESC, '.$sortorder.' DESC', $courses_limit)) {
			if ($mycourses = enrol_get_my_courses(NULL, 'startdate DESC, visible DESC', $courses_limit)) {
				foreach ($mycourses as $mycourse) {
					if ($CFG->navshowfullcoursenames) {
						$current_menu_item = $mycourse->fullname;
					} else {
						$current_menu_item = $mycourse->shortname;
					}
					$current_menu_item = format_string($current_menu_item, true, ['context' => context_course::instance(SITEID), "escape" => false]);
					$current_menu_item_title = format_string($mycourse->fullname, true, ['context' => context_course::instance(SITEID), "escape" => false]);
					$branch->add($current_menu_item, new moodle_url('/course/view.php', array('id' => $mycourse->id)), $current_menu_item_title);
				}
			} else {
				$branch->add(get_string('myhome'), new moodle_url('/my/index.php'), get_string('myhome'));
			}
		}

		$langs = get_string_manager()->get_list_of_translations();
		$haslangmenu = $this->lang_menu() != '';

		if (!$menu->has_children() && !$haslangmenu) {
			return '';
		}

		if ($haslangmenu) {
			$strlang = get_string('language');
			$currentlang = current_language();
			if (isset($langs[$currentlang])) {
				$currentlang = $langs[$currentlang];
			} else {
				$currentlang = $strlang;
			}
			$this->language = $menu->add($currentlang, new moodle_url(''), $strlang, 10000);
			foreach ($langs as $langtype => $langname) {
				$this->language->add($langname, new moodle_url($this->page->url, array('lang' => $langtype)), $langname);
			}
		}

		// if (isloggedin() && !isguestuser()) {
		// 	$branchsort = -1;
		// 	$branch = $menu->add(get_string('categories'), new moodle_url('/course/index.php'), get_string('categories'), $branchsort);
		// 	$branchsort = 100000;

		// 	$getcat_opts = [];
		// 	if (has_capability('moodle/category:viewhiddencategories', context_system::instance())) {
		// 		$getcat_opts = ['returnhidden'];
		// 	}
		// 	$allcate = core_course_category::get_all($getcat_opts);

		// 	$filtermanager = filter_manager::instance();
		// 	$filteroptions = array('originalformat' => FORMAT_HTML, 'noclean' => true);
		// 	$skipfilters = array('activitynames', 'data', 'glossary', 'sectionnames', 'bookchapters');
		// 	global $PAGE;

		// 	foreach ($allcate as $key => $cat) {
		// 		if ($cat->depth == 1) {

		// 			$catname = $filtermanager->filter_text($cat->name, $PAGE->context, $filteroptions, $skipfilters);
		// 			$branch2 = $branch->add($catname, $cat->get_view_link(), $catname, $branchsort);
		// 			if ($cat->visible == 0) {
		// 				$branch2->my_style = "color: #999;";
		// 			}

		// 			$children = $cat->get_children();
		// 			foreach ($children as $key => $catchild) {
		// 				$catname = $catchild->name;
		// 				$catname = $filtermanager->filter_text($catname, $PAGE->context, $filteroptions, $skipfilters);
		// 				$child_branch = $branch2->add($catname, $catchild->get_view_link(), $catname, $branchsort);

		// 				if ($cat->visible == 0) {
		// 					$child_branch->my_style = "color: #999;";
		// 				}

		// 			}
		// 		}
		// 	}
		// }

		$content = '<ul class="nav">';
		foreach ($menu->get_children() as $item) {
			$content .= $this->render_custom_menu_item($item, 1);
		}
		
		return $content . '</ul>';
	}

	public function render_login(\core_auth\output\login $form) {
        global $CFG, $SITE;

        $context = $form->export_for_template($this);

        $context->errorformatted = $this->error_text($context->error);
        $url = $this->get_logo_url();
        if ($url) {
            $url = $url->out(false);
        }
        $context->logourl = $url;
        $context->sitename = format_string($SITE->fullname, true,
                ['context' => context_course::instance(SITEID), "escape" => false]);

		if (strpos($CFG->release, '4.') === 0){
			return $this->render_from_template('theme_th_lambda/core/loginform', $context);
		} else if (strpos($CFG->release, '3.') === 0) {
			return $this->render_from_template('theme_th_lambda/core/loginform_v3', $context);
		} else {
			return $this->render_from_template('theme_th_lambda/core/loginform', $context);
		}
    }

	protected function render_custom_menu_item(custom_menu_item $menunode, $level = 0) {
		static $submenucount = 0;

		$content = '';
		if ($menunode->has_children()) {

			if ($level == 1) {
				$class = 'dropdown';
			} else {
				$class = 'dropdown-submenu';
			}

			if ($menunode === $this->language) {
				$class .= ' langmenu';
			}
			$content = html_writer::start_tag('li', array('class' => $class));
			// If the child has menus render it as a sub menu.
			$submenucount++;
			if ($menunode->get_url() !== null) {
				$url = $menunode->get_url();
			} else {
				$url = '#cm_submenu_' . $submenucount;
			}

			$cusattr = array('href' => $url, 'class' => 'dropdown-toggle', 'title' => $menunode->get_title());
			if (property_exists($menunode, 'my_style')) {
				$cusattr['style'] = $menunode->my_style;
			}

			if ($level == 1) {
				$cusattr['data-toggle'] = 'dropdown';
			}
			$content .= html_writer::start_tag('a', $cusattr);

			$content .= $menunode->get_text();
			if ($level == 1) {
				$content .= '<div class="caret"></div>';
			}
			$content .= '</a>';
			$content .= '<ul class="dropdown-menu">';
			foreach ($menunode->get_children() as $menunode) {
				$content .= $this->render_custom_menu_item($menunode, 0);
			}
			$content .= '</ul>';
		} else {
			// The node doesn't have children so produce a final menuitem.
			// Also, if the node's text matches '####', add a class so we can treat it as a divider.
			if (preg_match("/^#+$/", $menunode->get_text())) {
				// This is a divider.
				$content = '<li class="divider">&nbsp;</li>';
			} else {
				$content = '<li>';
				if ($menunode->get_url() !== null) {
					$url = $menunode->get_url();
				} else {
					$url = '#';
				}
				$cusattr = array('title' => $menunode->get_title());

				if (property_exists($menunode, 'my_style')) {
					$cusattr['style'] = $menunode->my_style;
				}
				$content .= html_writer::link($url, $menunode->get_text(), $cusattr);
				$content .= '</li>';
			}
		}
		return $content;
	}
}
