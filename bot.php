<?php
function handle($bot, $db, $lang, $extra_args = []) {
	extract($extra_args);
	if (!$bot->data) return; # if no data is passed
	$type = $bot->getUpdateType();
	
	if ($type == 'message') {
		$text = $bot->Text();
		$chat_id = $bot->ChatID();
		$user_id = $bot->UserID();
		$replied = $bot->ReplyToMessage();
		
		//$lang = setLanguage($user_id); # uncomment if you add translations
		$lang->language = 'ptbr';
		
		# change the sudoers list in config.php, then you can use /eval and /sql
		$is_sudoer = in_array($user_id, $sudoers);
		
		# if the bot is expecting a response for a command, $waiting is the info about the command, where w_for is what is the bot expecting, w_param is an additional/optional parameter and w_back is the callback_data to go back after the response or after the cancel
		$waiting = user_exists($user_id)? $db->query("SELECT w_for, w_param, w_back FROM users WHERE id={$user_id}")->fetch() : null;
		if ($waiting['w_for']) {
			extract($waiting);
			$keyb = ikb([
				[ [$lang->cancel, 'cancel'] ]
			]);
			
			if ($text == '/cancel') {
				update_user($user_id, ['w_for' => null, 'w_back' => null, 'w_param' => null]);
				$keyb = ikb([
					[ [$lang->back, $w_back] ],
				]);
				$bot->send($lang->command_canceled, ['reply_markup' => $keyb]);
			}
			else if ($w_for == 'domain') {
				if (!is_valid_domain($text)) {
					return $bot->reply($lang->invalid_domain, ['reply_markup' => $keyb]);
				}
				
				$stmt = $db->query('SELECT domain, last_check FROM domains WHERE id=? AND domain=?');
				$stmt->execute([$user_id, $text]);
				$exists = $stmt->fetch();
				
				if ($exists) {
					return $bot->reply($lang->domain_already_added, ['reply_markup' => $keyb]);
				}
				
				$stmt = $db->prepare('INSERT INTO domains (id, domain) VALUES (?, ?)');
				if ($stmt->execute([$user_id, $text])) {
					$keyb = ikb([
						[ [$lang->back, 'alerts'] ],
					]);
					update_user($user_id, ['w_for' => null, 'w_back' => null, 'w_param' => null]);
					return $bot->send($lang->domain_added, ['reply_markup' => $keyb]);
				}
				
				$bot->send($lang->domain_add_fail, ['reply_markup' => $keyb]);
			} else {
				$bot->send('bad w_for :(');
			}
		}
		else if ($text == '/start') {
			$keyb = ikb([
				[ [$lang->about, 'about'], [$lang->faq, 'faq'] ],
				[ [$lang->alerts, 'alerts'] ],
				[ [$lang->settings, 'settings'] ],
			]);
			$bot->send($lang->start_txt, ['reply_markup' => $keyb]);
		}
		else if ($text == '/alerts') {
			$domains = get_saved_domains($user_id);
			$tip_access = '';
			$lines = [[]];
			$line = 0;
			
			$domains_list = $lang->no_domains;
			if ($domains) {
				$str = $lang->registered_domains."\n";
				$count = 0;
				foreach ($domains as $domain => $row) {
					$count++;
					if (count($lines[$line]) >= 2) $line++;
					$lines[$line][] = [$lang->delete_domain(['key' => $count]), "delete_domain {$row['key']}"];
					$str .= " - $key. {$domain}\n";
				}
				$domains_list = $str;
			}
			$lines[] = [ [$lang->add_domain, "add_domain"] ];
			$keyb = ikb($lines);
			
			$args = compact('tip_access', 'domains_list');
			$msg = $lang->sentinel_info($args);
			$bot->send($msg, ['reply_markup' => $keyb]);
		}
		else if ($text == '/privacy') {
			$bot->action();
			$bot->send($lang->privacy);
		}
		else if ($text == '/help') {
			$bot->action();
			$bot->send($lang->faq_text);
		}
		else if ($text == '/about') {
			$bot->action();
			$bot->send($lang->about_text);
		}
		else if ($text == '/settings') {
			$keyb = ikb([
				#[ [$lang->language_btn, 'language'] ], # uncomment if you add translations
				[ [$lang->setting_translate_btn, 'setting_translate'] ]
			]);
			$msg = $lang->settings_text;
			$bot->send($msg, ['reply_markup' => $keyb]);
		}
		else if (is_valid_email($text)) {
			$bot->action();
			$breaches = $hibp->getBreachesForEmail($text);
			
			if ($hibp->getResponseCode() == '200'){
				$bot->protect();
				
				$settings = $db->querySingle("SELECT settings FROM users WHERE id={$user_id}");
				$settings = json_decode($settings, true);
				$translate = $settings['translate']['use'];
				$lang_code = $settings['translate']['lang_code'];
				
				$results = [];
				foreach ($breaches as $breach){
					$bot->action();
					
					$data = join(', ', $breach->data_classes);
					$data = htmlspecialchars_decode($data);
					$desc = strip_tags(htmlspecialchars_decode($breach->description));
					
					if ($translate) {
						$desc = translate($desc, $lang_code);
						$data = translate($data, $lang_code);
					}
					
					$arg = [
						'name' => $breach->name,
						'domain' => $breach->domain,
						'date' => format_date($breach->breach_date),
						'description' => $desc,
						'data' => $data,
						'count_pwned_accounts' => number_format($breach->pwn_count)
					];
					$results[] = $lang->result_text($arg);
				}
				
				$results = join("\n", $results);
				$msg = $lang->email_results_text(compact('results'));
				
				if (mb_strlen(strip_tags($msg)) > 4096){
					$text = strip_tags($msg);
					return $bot->indoc($text, 'results.txt');
				}
				$bot->send($msg);
			}
			else if ($hibp->getResponseCode() == '404'){
				$bot->action();
				$bot->send($lang->email_no_results_text);
			}
			else {
				$bot->action();
				$bot->send($lang->unknown_error($hibp->getResponseCode()));
			}
		}
		# command to execute php code through telegram
		else if (preg_match('#^/(\s+|ev(al)?\s+)(?<code>.+)#isu', $text, $match) && $is_sudoer) {
			$bot->protect(); # if the code take a long time to finish, this will prevent double-executing if the webhook send again the request
			$bot->action();
			
			# Saving the old values
			$before = [$handler->admin, $bot->debug, $bot->debug_admin];
			
			$handler->admin = $chat_id;
			$bot->debug = true;
			$bot->debug_admin = $chat_id;
			
			ob_start();
			try {
				eval($match['code']);
			} catch (Throwable $t) {
				echo $t;
			}
			$out = ob_get_contents();
			ob_end_clean();
			if ($out) {
				if (@$bot->sendMessage(['chat_id' => $chat_id, 'text' => $out, 'reply_to_message_id' => $bot->MessageID()])->ok != true) {
					$bot->indoc($out);
				}
			}
			
			# Setting back to the old values (important if you run handle() in a loop)
			$handler->admin = $before[0];
			$bot->debug = $before[1];
			$bot->debug_admin = $before[2];
		}
		# command to execute sql code through telegram
		else if (preg_match('#^/sql (?<sql>.+)#isu', $text, $match) && $is_sudoer) {
			extract($match);
			$query = $db->query($sql)->fetchAll();
			$bot->send(json_encode($query, 480));
		}
	}
	else if ($type == 'callback_query') {
		$call = $bot->CallbackQuery()['data'];
		$user_id = $bot->UserID();
		
		//$lang = setLanguage($user_id); # uncomment if you add translations
		$lang->language = 'ptbr';
		
		$is_sudoer = in_array($user_id, $sudoers);
		
		check_callback:
		if ($call == 'cancel') {
			# button present in the keyboard of all errors and requests of the bot to the user
			$call = $db->querySingle("SELECT w_back FROM users WHERE id={$user_id}");
			update_user($user_id, ['w_for' => null, 'w_back' => null, 'w_param' => null]);
			goto check_callback;
		}
		else if ($call == 'start') {
			# same message of /start
			# button present in the keyboard of all buttons in the $keyb below
			$keyb = ikb([
				[ [$lang->about, 'about'], [$lang->faq, 'faq'] ],
				[ [$lang->alerts, 'alerts'] ],
				[ [$lang->settings, 'settings'] ],
			]);
			$bot->edit($lang->start_txt, ['reply_markup' => $keyb]);
		}
		else if ($call == 'alerts') {
			# button present in the keyboard of /start
			list_domains:
			$domains = get_saved_domains($user_id);
			$tip_access = $lang->alerts_tip_access."\n";
			$lines = [[]];
			$line = 0;
			
			$domains_list = $lang->no_domains;
			if ($domains) {
				$str = $lang->registered_domains."\n";
				$count = 0;
				foreach ($domains as $domain => $row) {
					$count++;
					if (count($lines[$line]) >= 2) $line++;
					$lines[$line][] = [$lang->delete_domain(['key' => $count]), "delete_domain {$row['key']}"];
					$str .= " - $count. {$domain}\n";
				}
				$domains_list = $str;
			}
			$lines[] = [ [$lang->add_domain, "add_domain"] ];
			$lines[] = [ [$lang->back, 'start'] ];
			$keyb = ikb($lines);
			
			$args = compact('tip_access', 'domains_list');
			$msg = $lang->sentinel_info($args);
			$bot->edit($msg, ['reply_markup' => $keyb]);
		}
		else if (preg_match('#^delete_domain (?<key>.+)#', $call, $match)) {
			# button present in the keyboard of /alerts
			$key = $match['key'];
			$stmt = $db->prepare('DELETE FROM domains WHERE key=? AND id=?');
			$stmt->execute([$key, $user_id]);
			goto list_domains; # fetch the new domains list
		}
		else if ($call == 'add_domain') {
			# button present in the keyboard of /alerts
			update_user($user_id, ['w_for' => 'domain', 'w_back' => 'alerts']);
			$keyb = ikb([
				[ [$lang->cancel, 'cancel'] ],
			]);
			$bot->edit($lang->send_domain, ['reply_markup' => $keyb]);
		}
		else if ($call == 'about') {
			# button present in the keyboard of /start
			$keyb = ikb([
				[ [$lang->about_privacy, 'about_privacy'], [$lang->stats, 'stats'] ],
				[ [$lang->back, 'start'] ],
			]);
			$msg = $lang->about_text;
			$bot->edit($msg, ['reply_markup' => $keyb]);
		}
		else if ($call == 'about_privacy') {
			# button present in the keyboard of 'about' (button)
			$keyb = ikb([
				[ [$lang->back, 'about'] ],
			]);
			$bot->edit($lang->privacy, ['reply_markup' => $keyb]);
		}
		else if ($call == 'stats') {
			# button present in the keyboard of 'about' (button)
			$stats = json_decode(file_get_contents('strings/stats.json'), true);
			$keyb = ikb([
				[ [$lang->back, 'about'] ],
			]);
			$arg = [
				'total_users' => $db->querySingle('SELECT COUNT(key) FROM users'),
				'total_pwned_accounts' => $stats['total_pwned_acc'],
				'total_pwned_sites' => $stats['total_pwned_sites'],
			];
			$msg = $lang->stats_text($arg);
			$bot->edit($msg, ['reply_markup' => $keyb]);
		}
		else if ($call == 'faq') {
			# button present in the keyboard of /start
			$keyb = ikb([
				[ [$lang->back, 'start'] ],
			]);
			$msg = $lang->faq_text;
			$bot->edit($msg, ['reply_markup' => $keyb]);
		}
		else if ($call == 'settings') {
			# same message of /settings
			# button present in the keyboard of /start
			$keyb = ikb([
				#[ [$lang->language_btn, 'language'] ], # uncomment if you add translations
				[ [$lang->setting_translate_btn, 'setting_translate'] ],
				[ [$lang->back, 'start'] ],
			]);
			$msg = $lang->settings_text;
			$bot->edit($msg, ['reply_markup' => $keyb]);
		}
		else if ($call == 'language') {
			# button present in the keyboard of 'settings' (button)
			lang_menu:
			$lines = [[]];
			$line = 0;
			$lang->lang_name = $lang->lang_name.' ✅';
			foreach ($lang->data as $key => $obj) {
				if (count($lines[$line]) >= 2) $line++;
				$lines[$line][] = [$obj->lang_name ?? $key, "language_set {$key}"];
			}
			$lines[] = [ [$lang->back, 'settings'] ];
			$keyb = ikb($lines);
			$bot->edit($lang->language_select, ['reply_markup' => $keyb]);
		}
		else if (preg_match('#^language_set (?<key>.+)#', $call, $match)) {
			# button present in the keyboard of 'language' (button)
			$key = $match['key'];
			$same = $lang->language == $key;
			if ($same) {
				return $bot->answer_callback($lang->already_on_lang);
			}
			$lang->language = $key;
			update_user($user_id, ['language' => $key]);
			$bot->answer_callback($lang->language_selected);
			goto lang_menu; # fetch again the languages menu
		}
		else if ($call == 'setting_translate') {
			# button present in the keyboard of 'settings' (button)
			$settings = $db->querySingle("SELECT settings FROM users WHERE id={$user_id}");
			$settings = json_decode($settings, true);
			$tr = $settings['translate'];
			translator_settings:
			$codes = file_get_contents('strings/lang_codes.json');
			$codes = json_decode($codes, true);
			
			$lines = [
				[ [$lang->use_translator.($tr['use']? ' ✅' : ' ❌'), 'use_translator_switch'] ],
			];
			if ($tr['use']) {
				$lang_name = $codes[$tr['lang_code']];
				$lines[] = [ [$lang->selected_language_btn(['name' => $lang_name]), 'change_translator_lang 1'] ];
			}
			$lines[] = [ [$lang->back, 'settings'] ];
			$keyb = ikb($lines);
			$bot->edit($lang->settings_translate, ['reply_markup' => $keyb]);
		}
		else if ($call == 'use_translator_switch') {
			# button present in the keyboard of 'setting_translate' (button)
			$settings = $db->querySingle("SELECT settings FROM users WHERE id={$user_id}");
			$settings = json_decode($settings, true);
			$tr = &$settings['translate'];
			$tr['use'] = !$tr['use'];
			update_user($user_id, ['settings' => json_encode($settings)]);
			goto translator_settings;
		}
		else if (preg_match('#^change_translator_lang (?<page>\d+)#', $call, $match)) {
			# button present in the keyboard of 'setting_translate' (button)
			$page = $match['page'];
			$settings = $db->querySingle("SELECT settings FROM users WHERE id={$user_id}");
			$settings = json_decode($settings, true);
			$tr = $settings['translate'];
			$codes = file_get_contents('strings/lang_codes.json');
			$codes = json_decode($codes, true);
			$codes[$tr['lang_code']] .= ' ✅';
			$codes = array_slice($codes, ($page == 1? 0 : 99), ($page == 1? 98 : count($codes)), TRUE);
			
			$lines = [[]];
			$line = 0;
			foreach ($codes as $key => $name) {
				if (count($lines[$line]) >= 3) $line++;
				$lines[$line][] = [$name, "set_translator_language {$key}"];
			}
			$line++;
			$lines[$line] = [ [$lang->back, ($page == 1? "setting_translate" : "change_translator_lang 1")] ];
			if ($page == 1) {
				$lines[$line][] = [$lang->go, "change_translator_lang 2"];
			}
			$keyb = ikb($lines);
			$bot->edit($lang->select_translator_lang_text, ['reply_markup' => $keyb]);
		}
		else if (preg_match('#^set_translator_language (?<key>.+)#', $call, $match)) {
			# button present in the keyboard of 'change_translator_lang' (button)
			$key = $match['key'];
			$settings = $db->querySingle("SELECT settings FROM users WHERE id={$user_id}");
			$settings = json_decode($settings, true);
			$tr = &$settings['translate'];
			$tr['lang_code'] = $key;
			update_user($user_id, ['settings' => json_encode($settings)]);
			goto translator_settings;
		}
		
		# if you are sudoer, you can view the selected callback_data
		if ($is_sudoer) @$bot->answer_callback($call);
	}
}