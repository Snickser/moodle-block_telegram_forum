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
 * Observer Class.
 *
 * Long description for class (if any)...
 *
 * @package    block_telegram_forum
 * @copyright  2022 Marcelo Schmitt
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_telegram_forum_observer {

    /**
     * Event processor - user created
     *
     * @param \core\event\discussion_created $event
     * @return bool
     */
    public static function discussion_created(\mod_forum\event\discussion_created $event) {
        global $DB, $CFG;
        $context = context_course::instance($event->courseid);
        $instance = $DB->get_record('block_instances',
                        array('parentcontextid' => $context->id, 'blockname' => 'telegram_forum'));
        if (!$instance) {
            return true;
        } else {
            $blockname = 'telegram_forum';
            $block = block_instance($blockname, $instance);
            if (!isset($block->config->forum[$event->contextinstanceid])) {
                return true;
            }
            $discussion = $DB->get_record($event->objecttable, ['id' => $event->objectid]);
            $post = $DB->get_record('forum_posts', ['discussion' => $discussion->id]);
            $text = $post->subject . PHP_EOL . $post->message;
            self::preprocess_send_telegram_message($block->config->channelid, $text, $block->config->parsemode);
            return true;
        }
    }

    /**
     * Event when post is created
     *
     * @param \core\event\post_created $event
     * @return bool
     */
    public static function post_created(\mod_forum\event\post_created $event) {
        global $DB, $CFG;
        $context = context_course::instance($event->courseid);
        $instance = $DB->get_record('block_instances',
                        array('parentcontextid' => $context->id, 'blockname' => 'telegram_forum'));
        if (!$instance) {
            return true;
        } else {
            $blockname = 'telegram_forum';
            $block = block_instance($blockname, $instance);
            if (!isset($block->config->forummessage[$event->contextinstanceid])) {
                    return true;
            }
            $discussion = $DB->get_record($event->objecttable, ['id' => $event->objectid]);
            $post = $DB->get_record('forum_posts', ['id' => $discussion->id]);
            $text = $post->subject . PHP_EOL . $post->message;
            self::preprocess_send_telegram_message($block->config->channelid, $text, $block->config->parsemode);
            return true;
        }
    }


    /**
     * Method to preprocess send the message
     *
     * @param string $channelid - Channel Id of telegram
     * @param string $text - Text to be sent
     * @parsemode string $parsemode - Parse mode param
     * @return bool
     */
    public static function preprocess_send_telegram_message($channelid, $text, $parsemode='') {

$bottoken = get_config('block_telegram_forum', 'token');
$log = get_config('block_telegram_forum', 'telegramlog');
$logdump = get_config('block_telegram_forum', 'telegramlogdump');

if($parsemode=="HTML"){
    $text = strip_tags($text,"<b><strong><i><em><a><u><ins><code><pre><blockquote><tg-spoiler><tg-emoji>");
} else {
    $text = strip_tags($text);
}

$len=mb_strlen($text);
$max=4096;
for($i=0;$i<$len;$i+=$max-3){
    $tt = mb_substr($text,$i,$max-3,'UTF-8');
    if($len-$i>$max-3){
        $tt.="...";
        sleep(1);
    }
    self::send_telegram_message($bottoken, $channelid, $tt, $parsemode, $log, $logdump);
}

    }

    
    /**
     * Method to send the message
     *
     * @param string $bottoken - Token of telegram
     * @param string $channelid - Channel Id of telegram
     * @param string $text - Text to be sent
     * @parsemode string $parsemode - Parse mode param
     * @return bool
     */
    public static function send_telegram_message($bottoken, $channelid, $text, $parsemode='', $log=false, $logdump=false) {
        global $DB;
        $website = "https://api.telegram.org/bot".$bottoken;
        $params = [
            'chat_id' => $channelid,
            'text' => $text,
            'parse_mode' => "{$parsemode}",
        ];
        $curl = new curl();
        $url = $website . '/sendMessage';

$today = date("Y-m-d H:i:s");

$response = json_decode($curl->get($url, $params));

if($log){
    $buff = $today." ".$channelid." ".mb_strlen($text);
    if($response->ok == true) {
        $buff .= " ".$response->result->message_id;
    } else {
        $buff .= " ".$response->error_code." ".$response->description;
    }
    $buff .= "\n";
    if($logdump) $buff .= $text."\n";
    $fname = $CFG->dataroot.'/telegram.log';
    file_put_contents($fname, $buff, FILE_APPEND|LOCK_EX);
}
// for external sender
//$ttime=microtime(true);
//$fname = $CFG->dataroot.'/telegram/spool/'.$ttime;
//file_put_contents($fname, $channelid."\n".$text, FILE_APPEND|LOCK_EX);
       
        return true;
    }

}
