<?php

namespace William\HyperfExtTelegram\Job;

use finfo;
use Hyperf\AsyncQueue\Job;
use Telegram\Bot\Api;
use William\HyperfExtTelegram\Core\MessageBuilder;
use William\HyperfExtTelegram\Helper\Logger;
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
            try {
                $notice = TelegramNotice::find($post->notice_id);
                if ($notice) {
                    $botMap = [];
                    if ($post->to_all) {
                        $receivers = TelegramUser::get();
                    } else {
                        $query = TelegramUser::query();
                        if (!empty($post->bot_ids)) {
                            $query->whereIn('bot_ids', $post->bot_ids);
                        }
                        if (!empty($post->receivers)) {
                            $query->whereIn('id', $post->receivers);
                        }
                        $receivers = $query->get();
                    }
                    if (!empty($receivers)) {
                        if ($notice->attach) {
                            $relativePath = $notice->attach;
                            $absolutePath = realpath($relativePath);
                            // 判断文件是否存在
                            if ($absolutePath === false) {
                                Logger::error("发送公告失败，文件不存在：$absolutePath");
                                return;
                            }
                            $finfo = new finfo(FILEINFO_MIME_TYPE); // 获取 MIME 类型
                            $fileType = $finfo->file($absolutePath);
                            // 输出文件类型
                            Logger::debug("文件类型: " . $fileType);
                            // 如果你想按扩展名判断：
                            $fileExtension = pathinfo($absolutePath, PATHINFO_EXTENSION);
                            $baseName = pathinfo($absolutePath, PATHINFO_BASENAME);
                            Logger::debug("文件扩展名: " . $fileExtension);
                            $msgType = $this->getMessageType($fileType, $fileExtension);
                        } else {
                            $msgType = "message";
                        }

                        $inlineKeyboard = null;
                        if (!empty($notice->buttons)) {
                            $inlineKeyboard = $notice->buttons;
                        }


                        /** @var TelegramUser $receiver */
                        foreach ($receivers as $receiver) {
                            if (!isset($botMap[$receiver->bot_id])) {
                                $botMap[$receiver->bot_id] = TelegramBot::find($receiver->bot_id);
                            }
                            $bot = $botMap[$receiver->bot_id];
                            if ($bot) {
                                $telegram = new Api($bot->token);
                                $msg = MessageBuilder::newMessage($receiver->user_id);
                                if ($msgType !== 'message') {
                                    $msg->media($baseName, $msgType, 7200);
                                }
                                if ($inlineKeyboard) {
                                    $msg->inlineKeyboard($inlineKeyboard);
                                }
                                $msg->text($notice->content);
                                $msg->send($telegram);
                            }
                        }
                    }
                }
                $post->status = 1;
                $post->save();
                Logger::debug("发送公告成功：post_id={$post->id}");
            } catch (\Exception $e) {
                Logger::error("发送公告失败：{$e->getMessage()}{$e->getTraceAsString()}");
                $post->status = -1;
                $post->fail_reason = $e->getMessage();
                $post->save();
            }
        }
    }

    private function getMessageType(string $fileType, string $fileExtension)
    {
        if (str_starts_with($fileType, 'image/')) {
            // 图片文件
            $sendMethod = 'photo';
        } elseif (str_starts_with($fileType, 'audio/')) {
            // 音频文件
            $sendMethod = 'audio';
        } elseif (str_starts_with($fileType, 'video/')) {
            // 视频文件
            $sendMethod = 'video';
        } elseif (str_starts_with($fileType, 'application/pdf')) {
            // PDF 文件
            $sendMethod = 'document';
        } elseif (in_array(strtolower($fileExtension), ['jpg', 'jpeg', 'png', 'gif'])) {
            // 图片文件（根据扩展名进一步确认）
            $sendMethod = 'photo';
        } elseif (in_array(strtolower($fileExtension), ['mp3', 'wav', 'ogg'])) {
            // 音频文件（根据扩展名）
            $sendMethod = 'audio';
        } else {
            // 默认处理方法
            $sendMethod = 'document'; // 如果不匹配任何类型，默认用 sendDocument
        }
        return $sendMethod;
    }
}