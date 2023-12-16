<?php

flush();
ob_start();
ob_implicit_flush(1);

$version = '2.0.0';

// check the exists "Telebot Library"
if (!file_exists("telebot.php")) {
  copy("https://raw.githubusercontent.com/hctilg/telebot/v2.0/index.php", "telebot.php");
}

require('telebot.php');

function check_url(string $url) {
  $url = trim($url);
  $url = strtolower($url);
  $o = parse_url($url);
  
  if (!in_array($o['scheme'] ?? '', ['https', 'http'])) {
    throw new TypeError("URL must be of scheme HTTP or HTTPS");
  }

  if (strpos($url, 'redgifs') === false) {
    throw new TypeError("Invalid URL: $url");
  }

  return $url;
}

function get_content(string $url) {
  $context = stream_context_create($options = [
    "http" => [
      "header" => "User-Agent: Mozilla/5.0\r\n"
    ]
  ]);

  $response = @file_get_contents($url, false, $context);
  if ($response === false) throw new TypeError("Invalid URL: $url");

  preg_match('/https:\/\/api\.redgifs\.com\/v2\/gifs\/[\w-]+\/files\/[\w-]+\.mp4/', $response, $matches);
  return $content = $matches[0];
}

if ( // check cli mode
  $cli_mode = (php_sapi_name() === 'cli')
) {

  // parsing command line arguments
  $args = array(
    'url' => null,
    'file' => null,
    'wait' => 0.6,
    'no_save' => false,
    'telegram_bot' => false,
    'quite' => false,
    'help' => false
  );

  for ($i = 1; $i < count($argv); $i++) {
    if ($argv[$i] === '--help' || $argv[$i] === '-h') {
      $args['help'] = true;
    } elseif ($argv[$i] === '--telegram' || $argv[$i] === '-t') {
      $args['telegram_bot'] = true;
    } elseif ($argv[$i] === '--file' || $argv[$i] === '-f') {
      $args['file'] = $argv[++$i];
    } elseif ($argv[$i] === '--quite' || $argv[$i] === '-q') {
      $args['quite'] = true;
    } elseif ($argv[$i] === '--wait' || $argv[$i] === '-w') {
      $args['wait'] = floatval($argv[++$i]);
    } elseif ($argv[$i] === '--no-save' || $argv[$i] === '-ns') {
      $args['no_save'] = true;
    } else {
      $args['url'] = $argv[$i];
    }
  }

  $hepler = "
usage: $_SERVER[SCRIPT_NAME] [-h] [-f] [-t] [-q | -w | -ns] [url]

Download videos from redgifs.com
Version: $version

positional arguments:
url              The gif URL. If you want to use [--file] flag then this is can be omitted.

optional arguments:
-h, --help       show this help message and exit
-f , --file      File path of the URLs to download. A file need to be passed when using this flag.
-t , --telegram  Run as a Telegram bot.
-q, --quite      No verbose outputs.
-w , --wait      Wait for some seconds between each request (while using -f). Default: 6.9
-ns, --no-save   Don't save the video, just return the MP4 URL.
\n";

  if ($args['help']) {
    echo $hepler;
    exit(0);
  }
  
  if (!$args['telegram_bot']) {
    /**
     * Steps in-order.
     * 1. check url
     * 2. request html
     * 3. get content
     */

    if ($args['url'] ?? false) {
      try {
        $url = check_url($args['url'] ?? '');
        if (!$args['quite']) echo "\n[v] Sending request to $url";
        $link = get_content($url);

        if (!$args['quite']) echo "\n[v] Got video data";

        if ($args['no_save']) echo "$link\n";
        else {
          $dd = explode('/', $link);
          $filename = __DIR__ . '/' . end($dd);

          if (!$args['quite']) echo "\n[v] Downloading file..";

          copy($link, $filename, stream_context_create($options = [
            "http" => ["header" => "User-Agent: Mozilla/5.0\r\n"]
          ]));
          
          if (!$args['quite']) echo "\n[*] Downloaded: $filename";
        }
      } catch (\TypeError $e) {
        if (!$args['quite']) echo "\n[!] " . $e->getMessage() . "\n";
        exit();
      }
    }
    
    if ($args['file'] ?? false) {
      if (!file_exists($args['file'])) {
        if (!$args['quite']) echo "\n[!] File Not Found\n\n";
        exit();
      }

      $file = file_get_contents($args["file"]);
      $lines = explode("\n", $file);
      $urls = [];
      foreach ($lines as $line) {
        try {
          $urls[] = check_url($line);
        } catch (\TypeError $e) { }
      }
    
      $links = [];
      foreach ($urls as $url) {
        try {
          $links[] = get_content($url);
        } catch (\TypeError $e) { }
        sleep($args['wait']);
      }

      if ($args['no_save']) echo join("\n", array_unique($links));
      else {
        foreach ($links as $link) {
          $dd = explode('/', $link);
          $filename = __DIR__ . '/' . end($dd);

          if (!$args['quite']) echo "\n[v] Downloading file..";

          copy($link, $filename, stream_context_create($options = [
            "http" => ["header" => "User-Agent: Mozilla/5.0\r\n"]
          ]));
          
          if (!$args['quite']) echo "\n[*] Downloaded: $filename\n";
        }
      }
    }

    echo "\n";
    exit;
  }
}

/* ----------------------------------- Telegram Bot ----------------------------------- */

// get Token from @BotFather
// define("TOKEN", "<Your Token>");
define("TOKEN", "5981824622:AAGI5xtnXAXk-VHfnFb8XrV1b8O7Lm46vBk");

$bot = new Telebot(TOKEN, false);

function extract_links($text, $entities) {
  $links = [];

  foreach ($entities as $entity) {
    if (($entity['type'] ?? '') == 'text_link') {
      try {
        $links[] = check_url($entity['url']);
      } catch (\TypeError $e) { }
    } elseif (($entity['type'] ?? '') == 'text_mention') {
      try {
        $links[] = check_url($entity['url']);
      } catch (\TypeError $e) { }
    } elseif (($entity['type'] ?? '') == 'url') {
      $start = $entity['offset'];
      $length = $entity['length'];
      $link = substr($text, $start, $length);
      try {
        $links[] = check_url($link);
      } catch (\TypeError $e) { }
    }
  }

  return array_unique($links);
}

$bot->on('*', function($type, $data) use ($bot) {
  $chat_type = $data['chat']['type'] ?? $data['chat_type'] ?? 'unknown';
  if ($chat_type != 'private') return;

  $chat_id = $data['chat']['id'] ?? $data['from']['id'] ?? 'unknown';
  $message_id = $data['message_id'] ?? -1;
  $text = $data['text'] ?? '';

  $entities = $data['entities'] ?? [];
  $links = extract_links($text, $entities);
  if ($type == 'text' && $links != []) {
    $msg_ids = [];
    foreach ($links as $link) {
      try {
        $video = get_content($link);
        $_msg = $bot->sendVideo(['chat_id'=> $chat_id, 'video'=> "$video", 'caption'=> "Save it in **saved messages**.\nBecause it will be deleted in 30s", 'parse_mode'=> 'Markdown', 'reply_to_message_id'=> $message_id]);
        $mid = $_msg['result']['message_id'] ?? -1;
        $msg_ids[] = $mid;
        $bot->sendMessage(['chat_id'=> $chat_id, 'text'=> 'Have a hot story 😋', 'reply_markup'=> Telebot::inline_keyboard("[Open on Browser|url:$video]"), 'reply_to_message_id'=> $mid]);
      } catch (\TypeError $e) { }
      sleep(28);
      foreach ($msg_ids as $mid) $bot->deleteMessage(['chat_id'=> $chat_id, 'message_id'=> $mid]);
    }
  } else {
    $bot->sendMessage(['chat_id'=> $chat_id, 'text'=> "Send me links of your favorite videos ðŸ”¥", 'reply_to_message_id'=> $message_id]);
  }
});  

$bot->run();
