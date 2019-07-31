<?php
/*
A lite class to manage strings in i18n projects
- gist.github.com/usernein/1ab3de8f836f0a34c1180f133d91b860
Author: Cezar H. (t.me/usernein) (github.com/usernein)
How to use:
$lang = new Langs('strings.json');
or
# ptbr will be the default language (if the wanted string don't exist on other languages, the ptbr string is returned)
$lang = new Langs('strings.json', 'ptbr');

Check the examples at the bottom.
*/

class Langs {
	public $data = [];
	public $language = 'en';
	private $input;
	public $default_language = 'en';
	
	public function __construct(string $file, $default_language = 'en') {
		$this->input = $file;
		$json = file_get_contents($file);
		$this->data = json_decode($json);
		$this->language = $this->default_language = $default_language;
	}
	
	public function __get($key) {
		$language = $this->language;
		$default = $this->default_language;
		$string = $this->data->$language->$key ?? $this->data->$default->$key ?? $key;
		return $string;
	}
	
	public function __call($key, $args = []) {
		$args = $args[0];
		$language = $this->language;
		$default = $this->default_language;
		$string = $this->data->$language->$key ?? $this->data->$default->$key ?? $key;
		preg_match_all('#\{(?<var>[\w]+)\}#', $string, $match);
		foreach ($match['var'] as $var) {
			if (isset($args[$var])) {
				$string = str_replace('{'.$var.'}', $args[$var], $string);
			}
		}
		return $string;
	}
	
	public function __isset($key) {
		$language = $this->language;
		return isset($this->data->$language->$key);
	}
	
	public function __set($key, $value) {
		$language = $this->language;
		$this->data->$language->$key = $value;
	}
	
	public function save($output = null) {
		if (!$output) {
			$output = $this->input;
		}
		return file_put_contents($output, json_encode($this->data));
	}
}

/* #delete this "/*" and the last line to test
# strings.json is a json where the strings are saved. The structure should be {"lang_code":{"key":"string"}}, e.g.:
#{
#    "en": {
#        "greeting": "Hi!",
#        "greet_name": "Hi, {name}!"
#    },
#    "ptbr": {
#        "greeting": "Oi!",
#        "greet_name": "Oi, {name}!"
#    }
#}
# This object will be saved into the attribute $data
# The string keys and the {substituitions} should not be translated
# I personally use 'ptbr' instead of 'pt-BR' because some services may use 'pt-br', 'pt_br', 'pt_BR', etc. I just use strtolower and str_replace to support them all.

# After creating $lang, you'll need to set a language to use (if you don't want the default one):
# $lang->language = 'lang_code';
# example:
# $lang = new Langs('strings.json');
# $lang->language = 'ptbr';

# Then you can get any string by its key:
# echo $lang->string_key;

# Using our example of strings.json:
file_put_contents('test_strings.json', '{"en":{"greeting":"Hi!","greet_name":"Hi, {name}!"},"ptbr":{"greeting":"Oi!","greet_name":"Oi, {name}!"}}');
$lang = new Langs('test_strings.json');
$lang->language = 'ptbr';
echo $lang->greeting."\n"; // Oi!
$lang->language = 'en';
echo $lang->greeting."\n"; // Hi!
echo $lang->string_key."\n"; // string_key (if you try to get an unexistent key, itself will be returned)

# You can also get the strings directly from $data:
echo $lang->data->en->greeting."\n"; // Hi!
echo $lang->data->ptbr->greeting."\n"; // Oi!

# To make substituitions, call the string key passing an associative array with the {variable} and its substituitions:
echo $lang->greet_name(['name' => 'Cezar'])."\n"; // Hi, Cezar!
echo $lang->greet_name(['name' => ''])."\n"; // Hi, !
$lang->language = 'ptbr';
echo $lang->greet_name(['name' => 'Cezar'])."\n"; // Oi, Cezar!
$lang->language = 'en';

# If the string has a {variable} and no substituitions have been made, the {variable} will stat there:
echo $lang->greet_name."\n"; // Hi, {name}!

# You can set new values for the strings and add new keys:
$lang->greeting = 'Hello!'; // 'greeting' in 'en' (the current language) will be "Hello!"
$lang->data->ptbr->greeting = 'Olá!';

# To save the changes into the json, use:
$lang->save();
# or
$lang->save('new_test_strings.json'); # passing where to save the modified json

# Removing the test traces
@unlink('new_test_strings.json');
@unlink('test_strings.json');
*/