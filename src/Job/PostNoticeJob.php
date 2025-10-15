<?php

namespace William\HyperfExtTelegram\Job;

use Hyperf\AsyncQueue\Job;
use Telegram\Bot\Api;
use William\HyperfExtTelegram\Core\MessageBuilder;
use William\HyperfExtTelegram\Model\TelegramBot;
use William\HyperfExtTelegram\Model\TelegramNotice;
use William\HyperfExtTelegram\Model\TelegramNoticePost;
use William\HyperfExtTelegram\Model\TelegramUser;

class PostNoticeJob extends Job
{
    public function __construct(public int $noticeId)
    {
    }

    public function handle()
    {
        /** @var TelegramNoticePost $post */
        $post = TelegramNoticePost::find($this->noticeId);
        if ($post) {
            $notice = TelegramNotice::find($post->notice_id);
            if ($notice) {
                $botMap = [];
                if ($post->to_all) {
                    $receivers = TelegramUser::get();
                } else {
                    $receivers = TelegramUser::whereIn('id', $post->receivers)->get();
                }
                if (!empty($receivers)) {
                    /** @var TelegramUser $receiver */
                    foreach ($receivers as $receiver) {
                        if (!isset($botMap[$receiver->bot_id])) {
                            $botMap[$receiver->bot_id] = TelegramBot::find($receiver->bot_id);
                        }
                        $bot = $botMap[$receiver->bot_id];
                        if ($bot) {
                            $telegram = new Api($bot->token);
                            $msg = MessageBuilder::newMessage($receiver->user_id);
                            $msg->text($notice->content);
                            $msg->send($telegram);
                        }
                    }
                }
            }
        }
    }
}