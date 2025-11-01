<?php

namespace William\HyperfExtTelegram\Core;


use Hyperf\Cache\Cache;
use Illuminate\Support\Collection;
use Telegram\Bot\Objects\PhotoSize;
use William\HyperfExtTelegram\Helper\Logger;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Objects\Message;
use William\HyperfExtTelegram\Model\TelegramBot;
use function Hyperf\Config\config;
use function Hyperf\Support\make;
use function Hyperf\Translation\trans;

class MessageBuilder
{
    protected array $message = [];
    const FILE_KEY = 'tg_file:';
    protected string $locale = 'zh_CN';
    /**
     * @var bool $isEdit
     */
    protected bool $isEdit = false;
    protected int $messageId;
    protected string $messageType = 'Message';
    protected string $textField = 'text';
    /**
     * @var string|null
     */
    protected ?string $shouldSaveFileId = null;
    protected Cache $cache;
    protected int $fileIdExpires = 0;
    /**
     * @var mixed|string
     */
    protected mixed $botId = '';

    public function __construct()
    {
        // 默认初始化 parse_mode 为 HTML
        $this->message['parse_mode'] = 'HTML';
        $this->cache = make(Cache::class);
    }

    public static function newMessage($chatId, $botId = '')
    {
        $instance = new self();
        $instance->locale = LangContext::get();
        $instance->chatId($chatId);
        $instance->botId = $botId;
        return $instance;
    }

    public function messageType($type): self
    {
        $this->messageType = $type;
        if (ucfirst($this->messageType) !== 'Message') {
            $this->textField = 'caption';
        }
        return $this;
    }

    public function chatId(int|string $chatId): self
    {
        $this->message['chat_id'] = $chatId;
        return $this;
    }

    /**
     * 支持传入翻译 key 或纯文本
     */
    public function text(string $text): self
    {
        $this->message[$this->textField] = $text;
        return $this;
    }

    public function caption(string $text): self
    {
        $this->message['caption'] = $text;
        $this->textField = 'caption';
        return $this;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \RedisException
     */
    public function transMessage(string $key, array $params = []): self
    {
        $locale = LangContext::get();
        $text = trans('message.' . $key, $params, $locale);
        $this->message[$this->textField] = $text;
        return $this;
    }

    public function transNotification(string $key, array $params = []): self
    {
        $locale = LangContext::get();
        $this->message['text'] = trans('notifications.' . $key, $params, $locale);
        return $this;
    }


    public function newRow(): self
    {
        if (!isset($this->message['reply_markup'])) {
            $this->message['reply_markup'] = [];
        }
        if (!isset($this->message['reply_markup']['inline_keyboard'])) {
            $this->message['reply_markup']['inline_keyboard'] = [[]];
        } else {
            $this->message['reply_markup']['inline_keyboard'][] = [];
        }
        return $this;
    }

    public function inlineKeyboard($inlineKeyboard): self
    {
        if (!isset($this->message['reply_markup'])) {
            $this->message['reply_markup'] = [];
        }
        $this->message['reply_markup']['inline_keyboard'] = $inlineKeyboard;
        return $this;
    }

    public function addRow(array ...$buttons): self
    {
        $this->newRow();
        $rowIndex = count($this->message['reply_markup']['inline_keyboard']) - 1;
        foreach ($buttons as $button) {
            $this->message['reply_markup']['inline_keyboard'][$rowIndex][] = $button;
        }
        return $this;
    }

    public function disableWebPagePreview(): self
    {
        $this->message['disable_web_page_preview'] = true;
        return $this;
    }

    public function fileId(string $fileId, string $fileType): self
    {
        $this->textField = 'caption';
        $this->messageType = $fileType;
        $this->message[$fileType] = $fileId;
        return $this;
    }

    public function replyToMessageId($replyToMessageId): self
    {
        $this->message['reply_to_message_id'] = $replyToMessageId;
        return $this;
    }

    public function addButton(string $key, array $params = [], string $callbackData = '', bool $isLink = false, string $url = ''): self
    {
        if (!isset($this->message['reply_markup'])) {
            $this->message['reply_markup'] = [];
        }

        if (!isset($this->message['reply_markup']['inline_keyboard'])) {
            $this->message['reply_markup']['inline_keyboard'] = [[]]; // 初始化第一行
        }

        // 如果当前行为空，初始化一行
        if (empty($this->message['reply_markup']['inline_keyboard'])) {
            $this->message['reply_markup']['inline_keyboard'][] = [];
        }

        $rowIndex = count($this->message['reply_markup']['inline_keyboard']) - 1;
        $button = ['text' => trans("buttons.$key", $params)];

        if ($isLink) {
            $button['url'] = $url;
        } else {
            $button['callback_data'] = $callbackData ?: 'none';
        }

        $this->message['reply_markup']['inline_keyboard'][$rowIndex][] = $button;

        return $this;
    }


    /**
     * 获取最终构建好的消息数组
     */
    public function build(): array
    {
        if (isset($this->message['reply_markup']) && is_array($this->message['reply_markup'])) {
            $this->message['reply_markup'] = json_encode($this->message['reply_markup']);
        }
        return $this->message;
    }

    /**
     * @throws TelegramSDKException
     */
    public function send(Api $telegram): mixed
    {
        $type = ucfirst($this->messageType);
        $params = $this->build();
        if ($this->isEdit) {
            return $this->edit($telegram, $this->messageId);
        } else {
            $method = "send{$type}";
            Logger::info('send tg msg => ' . json_encode($params, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            /** @var Message $msg */
            $msg = call_user_func([$telegram, $method], $params);
            if ($this->shouldSaveFileId && $msg instanceof Message) {
                if ($fileId = $this->getResponseFileId($msg)) {
                    $redis = make(Cache::class);
                    $redis->set(self::FILE_KEY . $this->botId . ':' . $this->shouldSaveFileId, $fileId, $this->fileIdExpires);
                }
            }
            return $msg;
        }
    }

    protected function getResponseFileId(Message $msg): ?string
    {
        $fileId = null;
        // 🖼️ 图片
        if (!empty($msg->photo)) {
            // photo 是数组，一般最后一个分辨率最高
            /** @var Collection $photo */
            $photo = $msg->photo;
            /** @var PhotoSize $file */
            $file = $photo->last;
            if ($file->fileId) {
                /** @var PhotoSize $fi */
                $fi = $file->fileId;
                $fileId = $fi->fileId ?? null;
            }
        } // 📹 视频
        elseif (!empty($msg->video)) {
            $fileId = $msg->video->fileId;
        } // 🎬 动图（animation/gif）
        elseif (!empty($msg->animation)) {
            $fileId = $msg->animation->fileId;
        } // 🎧 音频
        elseif (!empty($msg->audio)) {
            $fileId = $msg->audio->fileId;
        } // 🎤 语音
        elseif (!empty($msg->voice)) {
            $fileId = $msg->voice->fileId;
        } // 📎 文件
        elseif (!empty($msg->document)) {
            $fileId = $msg->document->fileId;
        } // 😀 贴纸
        elseif (!empty($msg->sticker)) {
            $fileId = $msg->sticker->fileId;
        } // 🗣️ 视频语音消息（video_note）
        elseif (!empty($msg->videoNote)) {
            $fileId = $msg->videoNote->fileId;
        }
        return $fileId;
    }

    /**
     * @throws TelegramSDKException
     */
    public function edit(Api $telegram, $messageId): mixed
    {
        $this->message['message_id'] = $messageId;
        $params = $this->build();
        $type = ucfirst($this->textField);
        $method = "editMessage{$type}";
        Logger::info('edit tg msg => ' . json_encode($params, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return call_user_func([$telegram, $method], $params);
    }

    /**
     * @throws TelegramSDKException
     */
    public function alert(Api $telegram, $callbackQueryId, bool $showAlert = false): void
    {
        $this->message['callback_query_id'] = $callbackQueryId;
        if ($showAlert) {
            $params['show_alert'] = $showAlert;
        }
        Logger::info('AnswerCallbackQuery: ' . json_encode($params));
        $telegram->answerCallbackQuery($params);
    }

    public function replyMarkup(array $keyboards): self
    {
        if (!isset($this->message['reply_markup'])) {
            $this->message['reply_markup'] = [
                'keyboard' => $keyboards,   // 初始化第一行
                'resize_keyboard' => true, // 自动调整按钮大小
                'one_time_keyboard' => false, // 选择后是否隐藏
            ];
        }
        return $this;
    }

    public function setEdit(?int $messageId = null): self
    {
        if ($messageId) {
            $this->messageId = $messageId;
            $this->isEdit = true;
        }
        return $this;
    }

    public function photo(string $filename, int $expires = 0): self
    {
        $fileId = $this->getFileId($filename);
        $this->messageType = 'photo';
        $this->message['photo'] = $fileId;
        $this->textField = 'caption';
        $this->fileIdExpires = $expires;
        return $this;
    }

    public function media(string $filename, string $type, int $expires = 0): self
    {
        $fileId = $this->getFileId($filename);
        $this->messageType = $type;
        $this->message[$type] = $fileId;
        $this->textField = 'caption';
        $this->fileIdExpires = $expires;
        return $this;
    }

    protected function getFileId(string $filename): mixed
    {
        $redis = make(Cache::class);
        $fileId = $redis->get(self::FILE_KEY . $this->botId . ':' . $filename);
        if (!$fileId) {
            $videoPath = BASE_PATH . '/' . config('telegram.store_dir') . '/' . $filename;
            Logger::debug("媒体FileID缓冲不存在，重新上传");
            $fileId = InputFile::create($videoPath);
            $this->shouldSaveFileId = $filename;
        } else {
            Logger::debug("使用缓存FileID：$fileId");
        }
        return $fileId;
    }

    public function video(string $video, int $expires = 0): self
    {
        $fileId = $this->getFileId($video);
        $this->messageType = 'video';
        $this->message['video'] = $fileId;
        $this->textField = 'caption';
        $this->fileIdExpires = $expires;
        return $this;
    }
}
