<?php

namespace William\HyperfExtTelegram\Core;


use William\HyperfExtTelegram\Helper\CacheHelper;
use William\HyperfExtTelegram\Helper\Logger;
use Hyperf\Context\ApplicationContext;
use Hyperf\Redis\RedisFactory;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Objects\Message;
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

    public function __construct()
    {
        // 默认初始化 parse_mode 为 HTML
        $this->message['parse_mode'] = 'HTML';
    }

    public static function newMessage($chatId)
    {
        $instance = new self();
        $instance->locale = CacheHelper::getUserLang($chatId);
        $instance->chatId($chatId);
        return $instance;
    }

    public function messageType($type): self
    {
        $this->messageType = $type;
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
        $this->message['text'] = $text;
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
        $locale = CacheHelper::getUserLang($this->message['chat_id']);
        $text = trans('message.' . $key, $params, $locale);
        $this->message[$this->textField] = $text;
        return $this;
    }

    public function transNotification(string $key, array $params = []): self
    {
        $locale = CacheHelper::getUserLang($this->message['chat_id']);
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

    public function addRow(array ...$buttons): self
    {
        $this->newRow();
        $rowIndex = count($this->message['reply_markup']['inline_keyboard']) - 1;
        foreach ($buttons as $button) {
            $this->message['reply_markup']['inline_keyboard'][$rowIndex][] = $button;
        }
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
                $fileId = $msg->video->fileId;
                $redis = ApplicationContext::getContainer()->get(RedisFactory::class)->get('default');
                $redis->set(self::FILE_KEY . $this->shouldSaveFileId, $fileId);
            }
            return $msg;
        }
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

    public function setEdit(?int $messageId=null): self
    {
        if($messageId) {
            $this->messageId = $messageId;
            $this->isEdit = true;
        }
        return $this;
    }

    public function photo(mixed $fopen): self
    {
        $this->messageType = 'photo';
        $this->message['photo'] = $fopen;
        $this->textField = 'caption';
        return $this;
    }

    public function video(string $video): self
    {
        $redis = ApplicationContext::getContainer()->get(RedisFactory::class)->get('default');
        $videoId = $redis->get(self::FILE_KEY . $video);
        if (!$videoId) {
            $videoPath = BASE_PATH . '/runtime/uploads/' . $video;
            $videoId = InputFile::create($videoPath);
            $this->shouldSaveFileId = $video;
        }
        $this->messageType = 'video';
        $this->message['video'] = $videoId;
        $this->textField = 'caption';
        return $this;
    }
}
