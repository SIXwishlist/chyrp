<?php
	/**
	 * Class: Admin Controller
	 * The logic behind the Admin area.
	 */
	class AdminController {
		/**
		 * Variable: $context
		 * Contains the context for various admin pages, to be passed to the Twig templates.
		 */
		public $context = array();

		/**
		 * Function: write
		 * Post writing.
		 */
		public function write_post() {
			$visitor = Visitor::current();
			if (!$visitor->group()->can("add_post", "add_draft"))
				show_403(__("Access Denied"), __("You do not have sufficient privileges to create posts."));

			global $feathers;
			$this->context["feathers"]       = $feathers;
			$this->context["feather"]        = $feathers[fallback($_GET['feather'], Config::current()->enabled_feathers[0], true)];
			$this->context["GET"]["feather"] = fallback($_GET['feather'], Config::current()->enabled_feathers[0], true);
		}

		/**
		 * Function: add_post
		 * Adds a post when the form is submitted. Shows an error if the user lacks permissions.
		 */
		public function add_post() {
			$visitor = Visitor::current();
			if (!$visitor->group()->can("add_post", "add_draft"))
				show_403(__("Access Denied"), __("You do not have sufficient privileges to create posts."));

			if (!isset($_POST['hash']) or $_POST['hash'] != Config::current()->secure_hashkey)
				error(__("Access Denied"), __("Invalid security key."));

			global $feathers;
			$feathers[$_POST['feather']]->submit();
		}

		/**
		 * Function: edit_post
		 * Post editing.
		 */
		public function edit_post() {
			if (empty($_GET['id']))
				error(__("No ID Specified"), __("An ID is required to edit a post."));

			$this->context["post"] = new Post($_GET['id'], array("filter" => false));

			if (!$this->context["post"]->editable())
				show_403(__("Access Denied"), __("You do not have sufficient privileges to edit this post."));

			global $feathers;
			$this->context["feather"] = $feathers[$this->context["post"]->feather];
		}

		/**
		 * Function: update_post
		 * Updates a post when the form is submitted. Shows an error if the user lacks permissions.
		 */
		public function update_post() {
			$post = new Post($_POST['id']);
			if (!$post->editable())
				show_403(__("Access Denied"), __("You do not have sufficient privileges to edit this post."));

			if (!isset($_POST['hash']) or $_POST['hash'] != Config::current()->secure_hashkey)
				error(__("Access Denied"), __("Invalid security key."));

			global $feathers;
			$feathers[$post->feather]->update();

			if (!isset($_POST['ajax']))
				redirect("/admin/?action=manage_posts&updated=".$_POST['id']);
			else
				exit((string) $_POST['id']);
		}

		/**
		 * Function: delete_post
		 * Post deletion (confirm page).
		 */
		public function delete_post() {
			$this->context["post"] = new Post($_GET['id']);

			if (!$this->context["post"]->deletable())
				show_403(__("Access Denied"), __("You do not have sufficient privileges to delete this post."));
		}

		/**
		 * Function: destroy_post
		 * Destroys a post (the real deal).
		 */
		public function destroy_post() {
			if (empty($_POST['id']))
				error(__("No ID Specified"), __("An ID is required to delete a post."));

			if ($_POST['destroy'] == "bollocks")
				redirect("/admin/?action=manage_posts");

			if (!isset($_POST['hash']) or $_POST['hash'] != Config::current()->secure_hashkey)
				error(__("Access Denied"), __("Invalid security key."));

			$post = new Post($_POST['id']);
			if (!$post->deletable())
				show_403(__("Access Denied"), __("You do not have sufficient privileges to delete this post."));

			Post::delete($_POST['id']);

			redirect("/admin/?action=manage_posts&deleted=".$_POST['id']);
		}

		/**
		 * Function: manage_posts
		 * Post managing.
		 */
		public function manage_posts() {
			if (!Post::any_editable() and !Post::any_deletable())
				show_403(__("Access Denied"), __("You do not have sufficient privileges to manage any posts."));

			$params = array();
			$where = array();

			if (!empty($_GET['query'])) {
				$search = "";
				$matches = array();

				$queries = explode(" ", $_GET['query']);
				foreach ($queries as $query)
					if (!strpos($query, ":"))
						$search.= $query;
					else
						$matches[] = $query;

				foreach ($matches as $match) {
					$match = explode(":", $match);
					$test = $match[0];
					$equals = $match[1];
					if ($test == "author") {
						$user = new User(null, array("where" => "`login` = :login", "params" => array(":login" => $equals)));
						$test = "user_id";
						$equals = $user->id;
					}
					$where[] = "`__posts`.`".$test."` = :".$test;
					$params[":".$test] = $equals;
				}

				$where[] = "`__posts`.`xml` LIKE :query";
				$params[":query"] = "%".$search."%";
			}

			if (!empty($_GET['month'])) {
				$where[] = "`__posts`.`created_at` LIKE :when";
				$params[":when"] = $_GET['month']."-%";
			}

			$visitor = Visitor::current();
			if (!$visitor->group()->can("view_draft", "edit_draft", "edit_post", "delete_draft", "delete_post")) {
				$where[] = "`__posts`.`user_id` = :visitor_id";
				$params[':visitor_id'] = $visitor->id;
			}

			$this->context["posts"] = new Paginator(Post::find(array("placeholders" => true, "where" => $where, "params" => $params)), 25);

			if (!empty($_GET['updated']))
				$this->context["updated"] = new Post($_GET['updated']);

			$this->context["deleted"] = isset($_GET['deleted']);
		}

		/**
		 * Function: write_page
		 * Page creation.
		 */
		public function write_page() {
			if (!Visitor::current()->group()->can("add_page"))
				show_403(__("Access Denied"), __("You do not have sufficient privileges to create pages."));

			$this->context["pages"] = Page::find();
		}

		/**
		 * Function: add_page
		 * Adds a page when the form is submitted. Shows an error if the user lacks permissions.
		 */
		public function add_page() {
			if (!Visitor::current()->group()->can("add_page"))
				show_403(__("Access Denied"), __("You do not have sufficient privileges to create pages."));

			if (!isset($_POST['hash']) or $_POST['hash'] != Config::current()->secure_hashkey)
				error(__("Access Denied"), __("Invalid security key."));

			$show_in_list = !empty($_POST['show_in_list']);
			$clean = (!empty($_POST['slug'])) ? $_POST['slug'] : sanitize($_POST['title']) ;
			$url = Page::check_url($clean);

			$page = Page::add($_POST['title'], $_POST['body'], $_POST['parent_id'], $show_in_list, 0, $clean, $url);

			redirect($page->url());
		}

		/**
		 * Function: edit_page
		 * Page editing.
		 */
		public function edit_page() {
			if (!Visitor::current()->group()->can("edit_page"))
				show_403(__("Access Denied"), __("You do not have sufficient privileges to edit this page."));

			if (empty($_GET['id']))
				error(__("No ID Specified"), __("An ID is required to edit a page."));

			$this->context["page"] = new Page($_GET['id'], array("filter" => false));
			$this->context["pages"] = Page::find(array("where" => "`__pages`.`id` != :id", "params" => array(":id" => $_GET['id'])));
		}

		/**
		 * Function: update_page
		 * Updates a page when the form is submitted. Shows an error if the user lacks permissions.
		 */
		public function update_page() {
			if (!Visitor::current()->group()->can("edit_page"))
				show_403(__("Access Denied"), __("You do not have sufficient privileges to edit pages."));

			if (!isset($_POST['hash']) or $_POST['hash'] != Config::current()->secure_hashkey)
				error(__("Access Denied"), __("Invalid security key."));

			$page = new Page($_POST['id']);
			$page->update($_POST['title'], $_POST['body'], $_POST['parent_id'], !empty($_POST['show_in_list']), $page->list_order, $_POST['slug']);

			if (!isset($_POST['ajax']))
				redirect("/admin/?action=manage_pages&updated=".$_POST['id']);
		}

		/**
		 * Function: delete_page
		 * Page deletion (confirm page).
		 */
		public function delete_page() {
			if (!Visitor::current()->group()->can("delete_page"))
				show_403(__("Access Denied"), __("You do not have sufficient privileges to delete pages."));

			$this->context["page"] = new Page($_GET['id']);
		}

		/**
		 * Function: destroy_page
		 * Destroys a page.
		 */
		public function destroy_page() {
			if (!Visitor::current()->group()->can("delete_page"))
				show_403(__("Access Denied"), __("You do not have sufficient privileges to delete pages."));

			if (empty($_POST['id']))
				error(__("No ID Specified"), __("An ID is required to delete a post."));

			if ($_POST['destroy'] == "bollocks")
				redirect("/admin/?action=manage_pages");

			if (!isset($_POST['hash']) or $_POST['hash'] != Config::current()->secure_hashkey)
				error(__("Access Denied"), __("Invalid security key."));

			$page = new Page($_POST['id']);
			foreach ($page->children() as $child)
				if (isset($_POST['destroy_children']))
					Page::delete($child->id, true);
				else
					$child->update($child->title, $child->body, 0, $child->show_in_list, $child->list_order, $child->url);

			Page::delete($_POST['id']);

			redirect("/admin/?action=manage_pages&deleted=".$_POST['id']);
		}

		/**
		 * Function: manage_pages
		 * Page managing.
		 */
		public function manage_pages() {
			$visitor = Visitor::current();
			if (!$visitor->group()->can("edit_page") and !$visitor->group()->can("delete_page"))
				show_403(__("Access Denied"), __("You do not have sufficient privileges to manage pages."));

			$params = array();
			$where = array();

			if (!empty($_GET['query'])) {
				$search = "";
				$matches = array();

				$queries = explode(" ", $_GET['query']);
				foreach ($queries as $query)
					if (!strpos($query, ":"))
						$search.= $query;
					else
						$matches[] = $query;

				foreach ($matches as $match) {
					$match = explode(":", $match);
					$test = $match[0];
					$equals = $match[1];
					if ($test == "author") {
						$user = new User(null, array("where" => "`login` = :login", "params" => array(":login" => $equals)));
						$test = "user_id";
						$equals = ($user->no_results) ? 0 : $user->id ;
					}
					$where[] = "`__pages`.`".$test."` = :".$test;
					$params[":".$test] = $equals;
				}

				$where[] = "(`__pages`.`title` LIKE :query OR `__pages`.`body` LIKE :query)";
				$params[":query"] = "%".$search."%";
			}

			$this->context["pages"] = new Paginator(Page::find(array("placeholders" => true, "where" => $where, "params" => $params)), 25);

			if (!empty($_GET['updated']))
				$this->context["updated"] = new Page($_GET['updated']);

			$this->context["deleted"] = isset($_GET['deleted']);
		}

		/**
		 * Function: new_user
		 * User creation.
		 */
		public function new_user() {
			if (!Visitor::current()->group()->can("add_user"))
				show_403(__("Access Denied"), __("You do not have sufficient privileges to add users."));

			$config = Config::current();

			$this->context["default_group"] = new Group($config->default_group);
			$this->context["groups"] = Group::find(array("where" => array("`__groups`.`id` != :guest_id", "`__groups`.`id` != :default_id"),
			                                             "params" => array(":guest_id" => $config->guest_group, "default_id" => $config->default_group),
			                                             "order" => "`__groups`.`id` desc"));
		}

		/**
		 * Function: add_user
		 * Add a user when the form is submitted. Shows an error if the user lacks permissions.
		 */
		public function add_user() {
			if (!Visitor::current()->group()->can("add_user"))
				show_403(__("Access Denied"), __("You do not have sufficient privileges to add users."));

			if (!isset($_POST['hash']) or $_POST['hash'] != Config::current()->secure_hashkey)
				error(__("Access Denied"), __("Invalid security key."));

			if (empty($_POST['login']))
				error(__("Error"), __("Please enter a username for your account."));

			$check = new User(null, array("where" => "`login` = :login",
			                              "params" => array(":login" => $_POST['login'])));
			if (!$check->no_results)
				error(__("Error"), __("That username is already in use."));

			if (empty($_POST['password1']) or empty($_POST['password2']))
				error(__("Error"), __("Password cannot be blank."));
			if (empty($_POST['email']))
				error(__("Error"), __("E-mail address cannot be blank."));
			if ($_POST['password1'] != $_POST['password2'])
				error(__("Error"), __("Passwords do not match."));
			if (!eregi("^[[:alnum:]][a-z0-9_.-\+]*@[a-z0-9.-]+\.[a-z]{2,6}$",$_POST['email']))
				error(__("Error"), __("Unsupported e-mail address."));

			User::add($_POST['login'], $_POST['password1'], $_POST['email'], $_POST['full_name'], $_POST['website'], $_POST['group']);

			redirect("/admin/?action=manage_users&added");
		}

		/**
		 * Function: edit_user
		 * User editing.
		 */
		public function edit_user() {
			if (!Visitor::current()->group()->can("edit_user"))
				show_403(__("Access Denied"), __("You do not have sufficient privileges to edit this user."));

			if (empty($_GET['id']))
				error(__("No ID Specified"), __("An ID is required to edit a user."));

			$this->context["user"] = new User($_GET['id']);
			$this->context["groups"] = Group::find(array("order" => "`__groups`.`id` asc",
			                                             "where" => "`__groups`.`id` != :guest_id",
			                                             "params" => array(":guest_id" => Config::current()->guest_group)));
		}

		/**
		 * Function: update_user
		 * Updates a user when the form is submitted.
		 */
		public function update_user() {
			if (empty($_POST['id']))
				error(__("No ID Specified"), __("An ID is required to edit a user."));

			if (!isset($_POST['hash']) or $_POST['hash'] != Config::current()->secure_hashkey)
				error(__("Access Denied"), __("Invalid security key."));

			$visitor = Visitor::current();

			if (!$visitor->group()->can("edit_user"))
				show_403(__("Access Denied"), __("You do not have sufficient privileges to edit users."));

			$user = new User($_POST['id']);
			$password = (!empty($_POST['new_password1']) and $_POST['new_password1'] == $_POST['new_password2']) ?
			            md5($_POST['new_password1']) :
			            $user->password ;

			$user->update($_POST['login'], $password, $_POST['full_name'], $_POST['email'], $_POST['website'], $_POST['group']);

			if ($_POST['id'] == $visitor->id)
				$_SESSION['chyrp_password'] = $password;

			redirect("/admin/?action=manage_users&updated");
		}

		/**
		 * Function: delete_user
		 * User deletion.
		 */
		public function delete_user() {
			if (!Visitor::current()->group()->can("delete_user"))
				show_403(__("Access Denied"), __("You do not have sufficient privileges to delete users."));

			$this->context["user"] = new User($_GET['id']);
		}

		/**
		 * Function: destroy_user
		 * Destroys a user.
		 */
		public function destroy_user() {
			if (!Visitor::current()->group()->can("delete_user"))
				show_403(__("Access Denied"), __("You do not have sufficient privileges to delete users."));

			if (empty($_POST['id']))
				error(__("No ID Specified"), __("An ID is required to delete a user."));

			if ($_POST['destroy'] == "bollocks")
				redirect("/admin/?action=manage_users");

			if (!isset($_POST['hash']) or $_POST['hash'] != Config::current()->secure_hashkey)
				error(__("Access Denied"), __("Invalid security key."));

			User::delete($_POST['id']);

			redirect("/admin/?action=manage_users&deleted");
		}

		/**
		 * Function: manage_users
		 * User managing.
		 */
		public function manage_users() {
			$visitor = Visitor::current();
			if (!$visitor->group()->can("edit_user") and !$visitor->group()->can("delete_user") and !$visitor->group()->can("add_user"))
				show_403(__("Access Denied"), __("You do not have sufficient privileges to manage users."));

			$params = array();
			$where = array();

			if (!empty($_GET['query'])) {
				$search = "";
				$matches = array();

				$queries = explode(" ", $_GET['query']);
				foreach ($queries as $query)
					if (!strpos($query, ":"))
						$search.= $query;
					else
						$matches[] = $query;

				foreach ($matches as $match) {
					$match = explode(":", $match);
					$test = $match[0];
					$equals = $match[1];
					$where[] = "`__pages`.`".$test."` = :".$test;
					$params[":".$test] = $equals;
				}

				$where[] = "(`__users`.`login` LIKE :query OR `__users`.`full_name` LIKE :query OR `__users`.`email` LIKE :query OR `__users`.`website` LIKE :query)";
				$params[":query"] = "%".$_GET['query']."%";
			}

			$this->context["users"] = new Paginator(User::find(array("placeholders" => true, "where" => $where, "params" => $params)), 25);

			$this->context["updated"] = isset($_GET['updated']);
			$this->context["deleted"] = isset($_GET['deleted']);
			$this->context["added"]   = isset($_GET['added']);
		}

		/**
		 * Function: new_group
		 * Group creation.
		 */
		public function new_group() {
			if (!Visitor::current()->group()->can("add_group"))
				show_403(__("Access Denied"), __("You do not have sufficient privileges to create groups."));

			$this->context["permissions"] = SQL::current()->select("permissions")->fetchAll();
		}

		/**
		 * Function: add_group
		 * Adds a group when the form is submitted. Shows an error if the user lacks permissions.
		 */
		public function add_group() {
			if (!Visitor::current()->group()->can("add_group"))
				show_403(__("Access Denied"), __("You do not have sufficient privileges to create groups."));

			if (!isset($_POST['hash']) or $_POST['hash'] != Config::current()->secure_hashkey)
				error(__("Access Denied"), __("Invalid security key."));

			Group::add($_POST['name'], array_keys($_POST['permissions']));

			redirect("/admin/?action=manage_groups&added");
		}

		/**
		 * Function: edit_group
		 * Group editing.
		 */
		public function edit_group() {
			if (!Visitor::current()->group()->can("edit_group"))
				show_403(__("Access Denied"), __("You do not have sufficient privileges to edit groups."));

			$this->context["group"] = new Group($_GET['id']);
			$this->context["permissions"] = SQL::current()->select("permissions")->fetchAll();
		}

		/**
		 * Function: update_group
		 * Updates a group when the form is submitted. Shows an error if the user lacks permissions.
		 */
		public function update_group() {
			if (!Visitor::current()->group()->can("edit_group"))
				show_403(__("Access Denied"), __("You do not have sufficient privileges to edit groups."));

			if (!isset($_POST['hash']) or $_POST['hash'] != Config::current()->secure_hashkey)
				error(__("Access Denied"), __("Invalid security key."));

			$permissions = array_keys($_POST['permissions']);

			$group = new Group($_POST['id']);

			if ($group->no_results)
				redirect("/admin/?action=manage_groups ");

			$group->update($_POST['name'], $permissions);
			redirect("/admin/?action=manage_groups&updated");
		}

		/**
		 * Function: delete_group
		 * Group deletion (confirm page).
		 */
		public function delete_group() {
			if (!Visitor::current()->group()->can("delete_group"))
				show_403(__("Access Denied"), __("You do not have sufficient privileges to delete groups."));

			$this->context["group"] = new Group($_GET['id']);
			$this->context["groups"] = Group::find(array("where" => "`__groups`.`id` != :group_id",
			                                             "order" => "`__groups`.`id` asc",
			                                             "params" => array(":group_id" => $_GET['id'])));
		}

		/**
		 * Function: destroy_group
		 * Destroys a group.
		 */
		public function destroy_group() {
			if (!Visitor::current()->group()->can("delete_group"))
				show_403(__("Access Denied"), __("You do not have sufficient privileges to delete groups."));

			if (!isset($_POST['id']))
				error(__("No ID Specified"), __("An ID is required to delete a group."));

			if ($_POST['destroy'] == "bollocks")
				redirect("/admin/?action=manage_pages");

			if (!isset($_POST['hash']) or $_POST['hash'] != Config::current()->secure_hashkey)
				error(__("Access Denied"), __("Invalid security key."));

			$group = new Group($_POST['id']);
			foreach ($group->members() as $user)
				$user->update($user->login, $user->password, $user->full_name, $user->email, $user->website, $_POST['move_group']);

			$config = Config::current();
			if (!empty($_POST['default_group']))
				$config->set("default_group", $_POST['default_group']);
			if (!empty($_POST['guest_group']))
				$config->set("guest_group", $_POST['guest_group']);

			Group::delete($_POST['id']);

			redirect("/admin/?action=manage_groups&deleted");
		}

		/**
		 * Function: manage_groups
		 * Group managing.
		 */
		public function manage_groups() {
			$visitor = Visitor::current();
			if (!$visitor->group()->can("edit_group") and !$visitor->group()->can("delete_group") and !$visitor->group()->can("add_group"))
				show_403(__("Access Denied"), __("You do not have sufficient privileges to manage groups."));

			if (!empty($_GET['search'])) {
				$user = new User(null, array("where" => "`login` = :search", "params" => array(":search" => $_GET['search'])));
				$this->context["groups"] = array($user->group());
			} else
				$this->context["groups"] = new Paginator(Group::find(array("placeholders" => true, "order" => "`__groups`.`id` asc")), 10);

			$this->context["updated"] = isset($_GET['updated']);
			$this->context["deleted"] = isset($_GET['deleted']);
			$this->context["added"]   = isset($_GET['added']);
		}

		/**
		 * Function: export
		 * Export posts, pages, etc.
		 */
		public function export() {
			if (!Visitor::current()->group()->can("add_post"))
				show_403(__("Access Denied"), __("You do not have sufficient privileges to export content."));

			if (empty($_POST)) return;

			$config = Config::current();
			$trigger = Trigger::current();
			$route = Route::current();
			$exports = array();

			if (isset($_POST['posts'])) {
				if (!empty($_POST['filter_posts'])) {
					$search = "";
					$matches = array();

					$queries = explode(" ", $_POST['filter_posts']);
					foreach ($queries as $query)
						if (!strpos($query, ":"))
							$search.= $query;
						else
							$matches[] = $query;

					foreach ($matches as $match) {
						$match = explode(":", $match);
						$test = $match[0];
						$equals = $match[1];
						$where[] = "`__posts`.`".$test."` = :".$test;
						$params[":".$test] = $equals;
					}
				} else
					list($where, $params) = array(false, array());

				$posts = Post::find(array("where" => $where, "params" => $params, "order" => "`__posts`.`id` asc"),
				                    array("filter" => false));

				$latest_timestamp = 0;
				foreach ($posts as $post)
					if (strtotime($post->created_at) > $latest_timestamp)
						$latest_timestamp = strtotime($post->created_at);

				$id = substr(strstr($config->url, "//"), 2);
				$id = str_replace("#", "/", $id);
				$id = preg_replace("/(".preg_quote(parse_url($config->url, PHP_URL_HOST)).")/", "\\1,".date("Y", $latest_timestamp).":", $id, 1);

				$posts_atom = '<?xml version="1.0" encoding="utf-8"?>'."\r";
				$posts_atom.= '<feed xmlns="http://www.w3.org/2005/Atom" xmlns:chyrp="http://chyrp.net/export/1.0/">'."\r";
				$posts_atom.= '	<title>'.safe($config->name).' Posts</title>'."\r";
				$posts_atom.= '	<subtitle>'.safe($config->description).'</subtitle>'."\r";
				$posts_atom.= '	<id>tag:'.parse_url($config->url, PHP_URL_HOST).','.date("Y", $latest_timestamp).':Chyrp</id>'."\r";
				$posts_atom.= '	<updated>'.date("c", $latest_timestamp).'</updated>'."\r";
				$posts_atom.= '	<link href="'.$config->url.'" rel="self" type="application/atom+xml" />'."\r";
				$posts_atom.= '	<generator uri="http://chyrp.net/" version="'.CHYRP_VERSION.'">Chyrp</generator>'."\r";

				foreach ($posts as $post) {
					$title = fix($post->title(), false);
					fallback($title, ucfirst($post->feather)." Post #".$post->id);

					$updated = ($post->updated) ? $post->updated_at : $post->created_at ;

					$tagged = substr(strstr($route->url("id/".$post->id."/"), "//"), 2);
					$tagged = str_replace("#", "/", $tagged);
					$tagged = preg_replace("/(".preg_quote(parse_url($post->url(), PHP_URL_HOST)).")/", "\\1,".when("Y-m-d", $updated).":", $tagged, 1);

					$split = explode("\n", $post->xml);
					array_shift($split);
					$content = implode("\n", $split);
					$content = preg_replace("/(^<|<\/)post>/", "", $content);
					$content = str_replace("><", ">\n\t\t\t<", $content);

					$url = $post->url();
					$posts_atom.= '	<entry xml:base="'.safe($url).'">'."\r";
					$posts_atom.= '		<title type="html">'.$title.'</title>'."\r";
					$posts_atom.= '		<id>tag:'.$tagged.'</id>'."\r";
					$posts_atom.= '		<updated>'.when("c", $updated).'</updated>'."\r";
					$posts_atom.= '		<published>'.when("c", $post->created_at).'</published>'."\r";
					$posts_atom.= '		<link href="'.safe($trigger->filter($url, "post_export_url", $post)).'" />'."\r";
					$posts_atom.= '		<author chyrp:user_id="'.$post->user_id.'">'."\r";
					$posts_atom.= '			<name>'.safe(fallback($post->user()->full_name, $post->user()->login, true)).'</name>'."\r";

					if (!empty($post->user()->website))
						$posts_atom.= '			<uri>'.safe($post->user()->website).'</uri>'."\r";

					$posts_atom.= '			<chyrp:login>'.safe($post->user()->login).'</chyrp:login>'."\r";
					$posts_atom.= '		</author >'."\r";
					$posts_atom.= '		<content>'."\r";
					$posts_atom.= '			'.$content;
					$posts_atom.= '		</content>'."\r";

					foreach (array("feather", "clean", "url", "pinned", "status", "created_at", "updated_at") as $attr)
						$posts_atom.= '		<chyrp:'.$attr.'>'.safe($post->$attr).'</chyrp:'.$attr.'>'."\r";

					$trigger->filter($posts_atom, "posts_export", $post);

					$posts_atom.= '	</entry>'."\r";

				}
				$posts_atom.= '</feed>'."\r";

				$exports["posts.atom"] = $posts_atom;
			}

			if (isset($_POST['pages'])) {
				if (!empty($_POST['filter_pages'])) {
					$search = "";
					$matches = array();

					$queries = explode(" ", $_POST['filter_pages']);
					foreach ($queries as $query)
						if (!strpos($query, ":"))
							$search.= $query;
						else
							$matches[] = $query;

					foreach ($matches as $match) {
						$match = explode(":", $match);
						$test = $match[0];
						$equals = $match[1];
						$where[] = "`__pages`.`".$test."` = :".$test;
						$params[":".$test] = $equals;
					}
				} else
					list($where, $params) = array(null, array());

				$pages = Page::find(array("where" => $where, "params" => $params, "order" => "`__pages`.`id` asc"),
				                    array("filter" => false));

				$latest_timestamp = 0;
				foreach ($pages as $page)
					if (strtotime($page->created_at) > $latest_timestamp)
						$latest_timestamp = strtotime($page->created_at);

				$pages_atom = '<?xml version="1.0" encoding="utf-8"?>'."\r";
				$pages_atom.= '<feed xmlns="http://www.w3.org/2005/Atom" xmlns:chyrp="http://chyrp.net/export/1.0/">'."\r";
				$pages_atom.= '	<title>'.htmlspecialchars($config->name, ENT_NOQUOTES, "utf-8").' Pages</title>'."\r";
				$pages_atom.= '	<subtitle>'.htmlspecialchars($config->description, ENT_NOQUOTES, "utf-8").'</subtitle>'."\r";
				$pages_atom.= '	<id>tag:'.parse_url($config->url, PHP_URL_HOST).','.date("Y", $latest_timestamp).':Chyrp</id>'."\r";
				$pages_atom.= '	<updated>'.date("c", $latest_timestamp).'</updated>'."\r";
				$pages_atom.= '	<link href="'.$config->url.'" rel="self" type="application/atom+xml" />'."\r";
				$pages_atom.= '	<generator uri="http://chyrp.net/" version="'.CHYRP_VERSION.'">Chyrp</generator>'."\r";

				foreach ($pages as $page) {
					$updated = ($page->updated) ? $page->updated_at : $page->created_at ;

					$tagged = substr(strstr($page->url(), "//"), 2);
					$tagged = str_replace("#", "/", $tagged);
					$tagged = preg_replace("/(".preg_quote(parse_url($page->url(), PHP_URL_HOST)).")/", "\\1,".when("Y-m-d", $updated).":", $tagged, 1);

					$url = $page->url();
					$pages_atom.= '	<entry xml:base="'.safe($url).'" chyrp:parent_id="'.$page->parent_id.'">'."\r";
					$pages_atom.= '		<title type="html">'.safe($page->title).'</title>'."\r";
					$pages_atom.= '		<id>tag:'.$tagged.'</id>'."\r";
					$pages_atom.= '		<updated>'.when("c", $updated).'</updated>'."\r";
					$pages_atom.= '		<published>'.when("c", $page->created_at).'</published>'."\r";
					$pages_atom.= '		<link href="'.safe($trigger->filter($url, "page_export_url", $page)).'" />'."\r";
					$pages_atom.= '		<author chyrp:user_id="'.fix($page->user_id).'">'."\r";
					$pages_atom.= '			<name>'.safe(fallback($page->user()->full_name, $page->user()->login, true)).'</name>'."\r";

					if (!empty($page->user()->website))
						$pages_atom.= '			<uri>'.safe($page->user()->website).'</uri>'."\r";

					$pages_atom.= '			<chyrp:login>'.safe($post->user()->login).'</chyrp:login>'."\r";
					$pages_atom.= '		</author>'."\r";
					$pages_atom.= '		<content type="html">'.safe($page->body).'</content>'."\r";

					foreach (array("show_in_list", "list_order", "clean", "url", "created_at", "updated_at") as $attr)
						$pages_atom.= '		<chyrp:'.$attr.'>'.safe($page->$attr).'</chyrp:'.$attr.'>'."\r";


					$trigger->filter($pages_atom, "pages_export", $page);

					$pages_atom.= '	</entry>'."\r";
				}
				$pages_atom.= '</feed>'."\r";

				$exports["pages.atom"] = $pages_atom;
			}

			$trigger->filter($exports, "export");

			require INCLUDES_DIR."/lib/zip.php";

			$zip = new ZipFile();
			foreach ($exports as $filename => $content)
				$zip->addFile($content, $filename);

			$zip_contents = $zip->file();

			$filename = sanitize(camelize($config->name), false, true)."_Export_".now()->format("Y-m-d");
			header("Content-type: application/octet-stream");
			header("Content-Disposition: attachment; filename=\"".$filename.".zip\"");
			header("Content-length: ".strlen($zip_contents)."\n\n");

			echo $zip_contents;

			exit;
		}

		/**
		 * Function: import
		 * Importing content from other systems.
		 */
		public function import() {
			if (!Visitor::current()->group()->can("add_post"))
				show_403(__("Access Denied"), __("You do not have sufficient privileges to import content."));

			foreach (array("success_wordpress",
			               "invalid_wordpress",
			               "success_chyrp",
			               "invalid_chyrp_posts",
			               "invalid_chyrp_pages") as $message)
				$this->context[$message] = isset($_GET[$message]);
		}

		/**
		 * Function: import_chyrp
		 * Chyrp importing.
		 */
		public function import_chyrp() {
			$trigger = Trigger::current();

			if (isset($_FILES['posts_file']) and $_FILES['posts_file']['error'] == 0) {
				$posts = simplexml_load_file($_FILES['posts_file']['tmp_name']);

				if (!$posts or $posts->generator != "Chyrp")
					redirect("/admin/?action=import&invalid_chyrp_posts");
			}

			if (isset($_FILES['pages_file']) and $_FILES['pages_file']['error'] == 0) {
				$pages = simplexml_load_string(file_get_contents($_FILES['pages_file']['tmp_name']));

				if (!$pages or $pages->generator != "Chyrp")
					redirect("/admin/?action=import&invalid_chyrp_pages");
			}

			if (isset($_FILES['posts_file']) and $_FILES['posts_file']['error'] == 0)
				foreach ($posts->entry as $entry) {
					$chyrp = $entry->children("http://chyrp.net/export/1.0/");

					$_POST['feather'] = $chyrp->feather;
					$_POST['status']  = $chyrp->status;
					$_POST['pinned']  = (bool) (int) $chyrp->pinned;
					$_POST['created_at'] = $chyrp->created_at;
					$_POST['updated_at'] = $chyrp->updated_at;

					$data = Post::xml2arr($entry->content->post);
					$post = Post::add($data, $chyrp->clean, Post::check_url($chyrp->url));

					$trigger->call("import_chyrp_post", $entry, $post);
				}

			if (isset($_FILES['pages_file']) and $_FILES['pages_file']['error'] == 0)
				foreach ($pages->entry as $entry) {
					$chyrp = $entry->children("http://chyrp.net/export/1.0/");
					$attr  = $entry->attributes("http://chyrp.net/export/1.0/");
					$page = Page::add($entry->title,
					                  $entry->content,
					                  $attr->parent_id,
					                  (bool) (int) $chyrp->show_in_list,
					                  $chyrp->list_order,
					                  $chyrp->clean,
					                  Page::check_url($chyrp->url));

					$trigger->call("import_chyrp_page", $entry, $page);
				}

			redirect("/admin/?action=import&success_chyrp");
		}

		/**
		 * Function: import_wordpress
		 * WordPress importing.
		 */
		public function import_wordpress() {
			$trigger = Trigger::current();
			$config = Config::current();

			if (empty($_POST))
				redirect("/admin/?action=import");

			if (!in_array("text", $config->enabled_feathers))
				error(__("Missing Feather"), __("Importing from WordPress requires the Text feather to be installed and enabled."));

			if (ini_get("memory_limit") < 20) # Some imports are relatively large.
				ini_set("memory_limit", "20M");

			$stupid_xml = file_get_contents($_FILES['xml_file']['tmp_name']);
			$sane_xml = preg_replace(array("/<wp:comment_content>/", "/<\/wp:comment_content>/"),
			                         array("<wp:comment_content><![CDATA[", "]]></wp:comment_content>"),
			                         $stupid_xml);

			$sane_xml = str_replace(array("<![CDATA[<![CDATA[", "]]>]]>"),
			                        array("<![CDATA[", "]]>"),
			                        $sane_xml);

			$fix_amps_count = 1;
			while ($fix_amps_count)
				$sane_xml = preg_replace("/<wp:meta_value>(.+)&(?!amp;)(.+)<\/wp:meta_value>/m",
				                         "<wp:meta_value>\\1&amp;\\2</wp:meta_value>",
				                         $sane_xml, -1, $fix_amps_count);

			$xml = simplexml_load_string($sane_xml, "SimpleXMLElement", LIBXML_NOCDATA);

			if ($xml and strpos($xml->channel->generator, "wordpress.org")) {
				foreach ($xml->channel->item as $item) {
					$wordpress = $item->children("http://wordpress.org/export/1.0/");
					$content   = $item->children("http://purl.org/rss/1.0/modules/content/");
					if ($wordpress->status == "attachment" or $item->title == "zz_placeholder")
						continue;

					if (!empty($_POST['media_url']) and preg_match_all("/".preg_quote($_POST['media_url'], "/")."([^ \t\"]+)/", $content->encoded, $media))
						foreach ($media[0] as $matched_url) {
							$filename = upload_from_url($matched_url);
							$content->encoded = str_replace($matched_url, $config->url.$config->uploads_path.$filename, $content->encoded);
						}

					$clean = (isset($wordpress->post_name)) ? $wordpress->post_name : sanitize($item->title) ;

					if (empty($wordpress->post_type) or $wordpress->post_type == "post") {
						$status_translate = array("publish" => "public",
						                          "draft"   => "draft",
						                          "private" => "private",
						                          "static"  => "public",
						                          "object"  => "public",
						                          "inherit" => "public",
						                          "future"  => "draft",
						                          "pending" => "draft");

						$_POST['status']  = str_replace(array_keys($status_translate), array_values($status_translate), $wordpress->status);
						$_POST['pinned']  = false;
						$_POST['created_at'] = ($wordpress->post_date == "0000-00-00 00:00:00") ? datetime() : $wordpress->post_date ;
						$_POST['feather'] = "text";

						$data = array("title" => trim($item->title), "body" => trim($content->encoded));

						$post = Post::add($data, $clean, Post::check_url($clean));

						$trigger->call("import_wordpress_post", $item, $post);
					} elseif ($wordpress->post_type == "page") {
						$page = Page::add(trim($item->title), trim($content->encoded), 0, true, 0, $clean, Page::check_url($clean));
						$trigger->call("import_wordpress_page", $item, $post);
					}
				}

				redirect("/admin/?action=import&success_wordpress");
			} else
				redirect("/admin/?action=import&invalid_wordpress");
		}

		/**
		 * Function: extend_modules
		 * Module enabling/disabling.
		 */
		public function extend_modules() {
			if (!Visitor::current()->group()->can("toggle_extensions"))
				show_403(__("Access Denied"), __("You do not have sufficient privileges to enable/disable modules."));

			$config = Config::current();

			$this->context["enabled_modules"] = $this->context["disabled_modules"] = array();

			$issues = array();
			if ($open = opendir(MODULES_DIR)) {
				while (($folder = readdir($open)) !== false) {
					if (!file_exists(MODULES_DIR."/".$folder."/".$folder.".php") or !file_exists(MODULES_DIR."/".$folder."/info.yaml")) continue;

					if (file_exists(MODULES_DIR."/".$folder."/locale/".$config->locale.".mo"))
						load_translator($folder, MODULES_DIR."/".$folder."/locale/".$config->locale.".mo");

					$info = Spyc::YAMLLoad(MODULES_DIR."/".$folder."/info.yaml");

					$info["conflicts_true"] = array();

					if (!empty($info["conflicts"]))
						foreach ($info["conflicts"] as $conflict)
							if (file_exists(MODULES_DIR."/".$conflict."/".$conflict.".php")) {
								$issues[$folder] = true;
								$info["conflicts_true"][] = $conflict;
							}

					fallback($info["name"], $folder);
					fallback($info["version"], "0");
					fallback($info["url"]);
					fallback($info["description"]);
					fallback($info["author"], array("name" => "", "url" => ""));
					fallback($info["help"]);

					$info["description"] = __($info["description"], $folder);
					$info["description"] = preg_replace("/<code>(.+)<\/code>/se", "'<code>'.htmlspecialchars('\\1').'</code>'", $info["description"]);
					$info["description"] = preg_replace("/<pre>(.+)<\/pre>/se", "'<pre>'.htmlspecialchars('\\1').'</pre>'", $info["description"]);

					$info["author"]["link"] = (!empty($info["author"]["url"])) ?
					                              '<a href="'.htmlspecialchars($info["author"]["url"]).'">'.htmlspecialchars($info["author"]["name"]).'</a>' :
					                              $info["author"]["name"] ;

					$category = (module_enabled($folder)) ? "enabled_modules" : "disabled_modules" ;
					$this->context[$category][$folder] = array("name" => $info["name"],
					                                           "version" => $info["version"],
					                                           "url" => $info["url"],
					                                           "description" => $info["description"],
					                                           "author" => $info["author"],
					                                           "help" => $info["help"],
					                                           "conflict" => isset($issues[$folder]),
					                                           "conflicts" => $info["conflicts_true"],
					                                           "conflicts_class" => (isset($issues[$folder])) ? " conflict conflict_".join(" conflict_", $info["conflicts_true"]) : "");
				}
			}

			if (isset($_GET['enabled'])) {
				if (file_exists(MODULES_DIR."/".$_GET['enabled']."/locale/".$config->locale.".mo"))
					load_translator($_GET['enabled'], MODULES_DIR."/".$_GET['enabled']."/locale/".$config->locale.".mo");

				$info = Spyc::YAMLLoad(MODULES_DIR."/".$_GET['enabled']."/info.yaml");
				fallback($info["uploader"], false);
				fallback($info["notifications"], array());

				foreach ($info["notifications"] as &$notification)
					$notification = addslashes(__($notification, $_GET['enabled']));

				if ($info["uploader"])
					if (!file_exists(MAIN_DIR.$config->uploads_path))
						$info["notifications"][] = _f("Please create the <code>%s</code> directory at your Chyrp install's root and CHMOD it to 777.", array($config->uploads_path));
					elseif (!is_writable(MAIN_DIR.$config->uploads_path))
						$info["notifications"][] = _f("Please CHMOD <code>%s</code> to 777.", array($config->uploads_path));

				$this->context["notifications"] = $info["notifications"];
			}
		}

		/**
		 * Function: extend_feathers
		 * Feather enabling/disabling.
		 */
		public function extend_feathers() {
			if (!Visitor::current()->group()->can("toggle_extensions"))
				show_403(__("Access Denied"), __("You do not have sufficient privileges to enable/disable feathers."));

			$config = Config::current();

			$this->context["enabled_feathers"] = $this->context["disabled_feathers"] = array();

			if ($open = opendir(FEATHERS_DIR)) {
				while (($folder = readdir($open)) !== false) {
					if (!file_exists(FEATHERS_DIR."/".$folder."/".$folder.".php") or !file_exists(FEATHERS_DIR."/".$folder."/info.yaml")) continue;

					if (file_exists(FEATHERS_DIR."/".$folder."/locale/".$config->locale.".mo"))
						load_translator($folder, FEATHERS_DIR."/".$folder."/locale/".$config->locale.".mo");

					$info = Spyc::YAMLLoad(FEATHERS_DIR."/".$folder."/info.yaml");

					fallback($info["name"], $folder);
					fallback($info["version"], "0");
					fallback($info["url"]);
					fallback($info["description"]);
					fallback($info["author"], array("name" => "", "url" => ""));
					fallback($info["help"]);

					$info["description"] = __($info["description"], $folder);
					$info["description"] = preg_replace("/<code>(.+)<\/code>/se", "'<code>'.htmlspecialchars('\\1').'</code>'", $info["description"]);
					$info["description"] = preg_replace("/<pre>(.+)<\/pre>/se", "'<pre>'.htmlspecialchars('\\1').'</pre>'", $info["description"]);

					$info["author"]["link"] = (!empty($info["author"]["url"])) ?
					                              '<a href="'.htmlspecialchars($info["author"]["url"]).'">'.htmlspecialchars($info["author"]["name"]).'</a>' :
					                              $info["author"]["name"] ;

					$category = (feather_enabled($folder)) ? "enabled_feathers" : "disabled_feathers" ;
					$this->context[$category][$folder] = array("name" => $info["name"],
					                                           "version" => $info["version"],
					                                           "url" => $info["url"],
					                                           "description" => $info["description"],
					                                           "author" => $info["author"],
					                                           "help" => $info["help"]);
				}
			}

			if (isset($_GET['enabled'])) {
				if (file_exists(FEATHERS_DIR."/".$_GET['enabled']."/locale/".$config->locale.".mo"))
					load_translator($_GET['enabled'], FEATHERS_DIR."/".$_GET['enabled']."/locale/".$config->locale.".mo");

				$info = Spyc::YAMLLoad(FEATHERS_DIR."/".$_GET['enabled']."/info.yaml");
				fallback($info["uploader"], false);
				fallback($info["notifications"], array());

				foreach ($info["notifications"] as &$notification)
					$notification = addslashes(__($notification, $_GET['enabled']));

				if ($info["uploader"])
					if (!file_exists(MAIN_DIR.$config->uploads_path))
						$info["notifications"][] = _f("Please create the <code>%s</code> directory at your Chyrp install's root and CHMOD it to 777.", array($config->uploads_path));
					elseif (!is_writable(MAIN_DIR.$config->uploads_path))
						$info["notifications"][] = _f("Please CHMOD <code>%s</code> to 777.", array($config->uploads_path));

				$this->context["notifications"] = $info["notifications"];
			}
		}

		/**
		 * Function: extend_themes
		 * Theme switching/previewing.
		 */
		public function extend_themes() {
			$config = Config::current();

			$this->context["themes"] = array();
			$this->context["changed"] = isset($_GET['changed']);

			if ($open = opendir(THEMES_DIR)) {
			     while (($folder = readdir($open)) !== false) {
					if (!file_exists(THEMES_DIR."/".$folder."/info.yaml"))
						continue;

					if (file_exists(THEMES_DIR."/".$folder."/locale/".$config->locale.".mo"))
						load_translator($folder, THEMES_DIR."/".$folder."/locale/".$config->locale.".mo");

					$info = Spyc::YAMLLoad(THEMES_DIR."/".$folder."/info.yaml");

					fallback($info["name"], $folder);
					fallback($info["version"], "0");
					fallback($info["url"]);
					fallback($info["description"]);
					fallback($info["author"], array("name" => "", "url" => ""));

					$info["author"]["link"] = (!empty($info["author"]["url"])) ?
					                              '<a href="'.$info["author"]["url"].'">'.$info["author"]["name"].'</a>' :
					                              $info["author"]["name"] ;
					$info["description"] = preg_replace("/<code>(.+)<\/code>/se", "'<code>'.htmlspecialchars('\\1').'</code>'", $info["description"]);
					$info["description"] = preg_replace("/<pre>(.+)<\/pre>/se", "'<pre>'.htmlspecialchars('\\1').'</pre>'", $info["description"]);

					$this->context["themes"][] = array("name" => $folder,
					                                   "screenshot" => (file_exists(THEMES_DIR."/".$folder."/screenshot.png") ?
					                                                       $config->chyrp_url."/themes/".$folder."/screenshot.png" :
					                                                       $config->chyrp_url."/admin/images/noscreenshot.png"),
					                                   "info" => $info);
				}
				closedir($open);
			}
		}

		/**
		 * Function: enable
		 * Enables a module or feather.
		 */
		public function enable() {
			$config  = Config::current();
			$visitor = Visitor::current();

			$type = (isset($_GET['module'])) ? "module" : "feather" ;

			if (!$visitor->group()->can("toggle_extensions"))
				if ($type == "module")
					show_403(__("Access Denied"), __("You do not have sufficient privileges to enable/disable modules."));
				else
					show_403(__("Access Denied"), __("You do not have sufficient privileges to enable/disable feathers."));

			if (($type == "module" and module_enabled($_GET[$type])) or
			    ($type == "feather" and feather_enabled($_GET[$type])))
				redirect("/admin/?action=extend_modules");

			$enabled_array = ($type == "module") ? "enabled_modules" : "enabled_feathers" ;
			$folder        = ($type == "module") ? MODULES_DIR : FEATHERS_DIR ;

			require $folder."/".$_GET[$type]."/".$type.".php";

			$class_name = camelize($_GET[$type]);
			if (method_exists($class_name, "__install"))
				call_user_func(array($class_name, "__install"));

			$new = $config->$enabled_array;
			array_push($new, $_GET[$type]);
			$config->set($enabled_array, $new);

			redirect("/admin/?action=extend_".$type."s&enabled=".$_GET[$type]);
		}

		/**
		 * Function: disable
		 * Disables a module or feather.
		 */
		public function disable() {
			$config  = Config::current();
			$visitor = Visitor::current();

			$type = (isset($_GET['module'])) ? "module" : "feather" ;

			if (!$visitor->group()->can("toggle_extensions"))
				if ($type == "module")
					show_403(__("Access Denied"), __("You do not have sufficient privileges to enable/disable modules."));
				else
					show_403(__("Access Denied"), __("You do not have sufficient privileges to enable/disable feathers."));

			if (($type == "module" and !module_enabled($_GET[$type])) or
			    ($type == "feather" and !feather_enabled($_GET[$type])))
				redirect("/admin/?action=extend_modules");

			$enabled_array = ($type == "module") ? "enabled_modules" : "enabled_feathers" ;
			$folder        = ($type == "module") ? MODULES_DIR : FEATHERS_DIR ;

			$class_name = camelize($_GET[$type]);
			if (method_exists($class_name, "__uninstall"))
				call_user_func(array($class_name, "__uninstall"), false);

			$config->set(($type == "module" ? "enabled_modules" : "enabled_feathers"),
			             array_diff($config->$enabled_array, array($_GET[$type])));

			redirect("/admin/?action=extend_".$type."s&enabled=".$_GET[$type]);
		}

		/**
		 * Function: general_settings
		 * General Settings page.
		 */
		public function general_settings() {
			if (!Visitor::current()->group()->can("change_settings"))
				show_403(__("Access Denied"), __("You do not have sufficient privileges to change settings."));

			$this->context["updated"] = isset($_GET['updated']);
			$this->context["locales"] = array();

			if ($open = opendir(INCLUDES_DIR."/locale/")) {
			     while (($folder = readdir($open)) !== false) {
					$split = explode(".", $folder);
					if (end($split) == "mo")
						$this->context["locales"][] = array("code" => $split[0], "name" => lang_code($split[0]));
				}
				closedir($open);
			}

			$this->context["timezones"] = timezones(true);

			if (empty($_POST))
				return;

			if (!isset($_POST['hash']) or $_POST['hash'] != Config::current()->secure_hashkey)
				error(__("Access Denied"), __("Invalid security key."));

			$config = Config::current();
			$config->set("name", $_POST['name']);
			$config->set("description", $_POST['description']);
			$config->set("chyrp_url", rtrim($_POST['chyrp_url'], '/'));
			$config->set("url", rtrim(fallback($_POST['url'], $_POST['chyrp_url'], true), '/'));
			$config->set("email", $_POST['email']);
			$config->set("timezone", $_POST['timezone']);
			$config->set("locale", $_POST['locale']);

			redirect("/admin/?action=general_settings&updated");
		}

		/**
		 * Function: user_settings
		 * User Settings page.
		 */
		public function user_settings() {
			if (!Visitor::current()->group()->can("change_settings"))
				show_403(__("Access Denied"), __("You do not have sufficient privileges to change settings."));

			$this->context["updated"] = isset($_GET['updated']);
			$this->context["groups"] = Group::find(array("order" => "`__groups`.`id` desc"));

			if (empty($_POST))
				return;

			if (!isset($_POST['hash']) or $_POST['hash'] != Config::current()->secure_hashkey)
				error(__("Access Denied"), __("Invalid security key."));

			$config = Config::current();
			$config->set("can_register", !empty($_POST['can_register']));
			$config->set("default_group", $_POST['default_group']);
			$config->set("guest_group", $_POST['guest_group']);

			redirect("/admin/?action=user_settings&updated");
		}

		/**
		 * Function: content_settings
		 * Content Settings page.
		 */
		public function content_settings() {
			if (!Visitor::current()->group()->can("change_settings"))
				show_403(__("Access Denied"), __("You do not have sufficient privileges to change settings."));

			$this->context["updated"] = isset($_GET['updated']);

			if (empty($_POST))
				return;

			if (!isset($_POST['hash']) or $_POST['hash'] != Config::current()->secure_hashkey)
				error(__("Access Denied"), __("Invalid security key."));

			$config = Config::current();
			$config->set("feed_items", $_POST['feed_items']);
			$config->set("feed_url", $_POST['feed_url']);
			$config->set("enable_trackbacking", !empty($_POST['enable_trackbacking']));
			$config->set("send_pingbacks", !empty($_POST['send_pingbacks']));
			$config->set("posts_per_page", $_POST['posts_per_page']);
			$config->set("enable_xmlrpc", !empty($_POST['enable_xmlrpc']));

			redirect("/admin/?action=content_settings&updated");
		}

		/**
		 * Function: route_settings
		 * Route Settings page.
		 */
		public function route_settings() {
			if (!Visitor::current()->group()->can("change_settings"))
				show_403(__("Access Denied"), __("You do not have sufficient privileges to change settings."));

			$this->context["updated"] = isset($_GET['updated']);

			if (empty($_POST))
				return;

			if (!isset($_POST['hash']) or $_POST['hash'] != Config::current()->secure_hashkey)
				error(__("Access Denied"), __("Invalid security key."));

			$config = Config::current();
			$config->set("clean_urls", !empty($_POST['clean_urls']));
			$config->set("post_url", $_POST['post_url']);

			redirect("/admin/?action=route_settings&updated");
		}

		/**
		 * Function: change_theme
		 * Changes the theme. Shows an error if the user lacks permissions.
		 */
		public function change_theme() {
			if (!Visitor::current()->group()->can("change_settings"))
				show_403(__("Access Denied"), __("You do not have sufficient privileges to change settings."));
			if (empty($_GET['theme']))
				error(__("No Theme Specified"), __("You did not specify a theme to switch to."));

			Config::current()->set("theme", $_GET['theme']);

			redirect("/admin/?action=extend_themes&changed");
		}

		/**
		 * Function: reorder_pages
		 * Reorders pages.
		 */
		public function reorder_pages() {
			foreach ($_POST['list_order'] as $id => $order) {
				$page = new Page($id);
				$page->update($page->title, $page->body, $page->parent_id, $page->show_in_list, $order, $page->url);
			}
			redirect("/admin/?action=manage_pages&reordered");
		}

		/**
		 * Function: determine_action
		 * Determines through simple logic which page should be shown as the default when browsing to /admin/.
		 */
		public function determine_action() {
			$visitor = Visitor::current();

			# "Write > Post", if they can add posts or drafts.
			if ($visitor->group()->can("add_post") or $visitor->group()->can("add_draft"))
				return "write_post";

			# "Write > Page", if they can add pages.
			if ($visitor->group()->can("add_page"))
				return "write_page";

			# "Manage > Posts", if they can manage any posts.
			if (Post::any_editable() or Post::any_deletable())
				return "manage_posts";

			# "Manage > Pages", if they can manage pages.
			if ($visitor->group()->can("edit_page") or $visitor->group()->can("delete_page"))
				return "manage_pages";

			# "Manage > Users", if they can manage users.
			if ($visitor->group()->can("edit_user") or $visitor->group()->can("delete_user"))
				return "manage_users";

			# "Manage > Groups", if they can manage groups.
			if ($visitor->group()->can("edit_group") or $visitor->group()->can("delete_group"))
				return "manage_groups";

			# "Settings", if they can configure the installation.
			if ($visitor->group()->can("change_settings"))
				return "settings";

			show_403(__("Access Denied"), __("You do not have sufficient privileges to access this area."));
		}
	}
	$admin = new AdminController();
