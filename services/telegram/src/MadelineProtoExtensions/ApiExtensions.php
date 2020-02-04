<?php


namespace TelegramApiServer\MadelineProtoExtensions;


use Amp\ByteStream\IteratorStream;
use Amp\Http\Server\Request;
use Amp\Producer;
use Amp\Promise;
use danog\MadelineProto\TL\Conversion\BotAPI;
use OutOfRangeException;
use TelegramApiServer\EventObservers\EventHandler;
use UnexpectedValueException;
use function Amp\call;
use \danog\MadelineProto;

class ApiExtensions
{
    use BotAPI;

    private MadelineProto\Api $madelineProto;
    private Request $request;

    public function __construct(MadelineProto\Api $madelineProto, Request $request)
    {
        $this->madelineProto = $madelineProto;
        $this->request = $request;
    }

    /**
     * Получает последние сообщения из указанных каналов
     *
     * @param array $data
     * <pre>
     * [
     *     'peer' => '',
     *     'offset_id' => 0, // (optional)
     *     'offset_date' => 0, // (optional)
     *     'add_offset' => 0, // (optional)
     *     'limit' => 0, // (optional)
     *     'max_id' => 0, // (optional)
     *     'min_id' => 0, // (optional)
     *     'hash' => 0, // (optional)
     * ]
     * </pre>
     *
     * @return MadelineProto\messages|Promise
     */
    public function getHistory(array $data): Promise
    {
        $data = array_merge(
            [
                'peer' => '',
                'offset_id' => 0,
                'offset_date' => 0,
                'add_offset' => 0,
                'limit' => 0,
                'max_id' => 0,
                'min_id' => 0,
                'hash' => 0,
            ],
            $data
        );

        return $this->madelineProto->messages->getHistory($data);
    }

    /**
     * @param array $data
     *
     * @return Promise
     */
    public function getHistoryHtml(array $data): Promise
    {
        return call(
            function() use ($data) {
                $response = yield $this->getHistory($data);

                foreach ($response['messages'] as &$message) {
                    $message['message'] = $this->formatMessage($message['message'] ?? null, $message['entities'] ?? []);
                }
                unset($message);

                return $response;
            }
        );
    }

    /**
     * Проверяет есть ли подходящие медиа у сообщения
     *
     * @param array $message
     * @param bool $allowWebPage
     *
     * @return bool
     */
    private static function hasMedia(array $message = [], bool $allowWebPage = true): bool
    {
        $media = $message['media'] ?? [];
        if (empty($media['_'])) {
            return false;
        }
        if ($media['_'] === 'messageMediaWebPage') {
            return $allowWebPage;
        }
        return true;
    }

    public function formatMessage(string $message = null, array $entities = []): ?string
    {
        $html = [
            'messageEntityItalic' => '<i>%s</i>',
            'messageEntityBold' => '<strong>%s</strong>',
            'messageEntityCode' => '<code>%s</code>',
            'messageEntityPre' => '<pre>%s</pre>',
            'messageEntityStrike' => '<strike>%s</strike>',
            'messageEntityUnderline' => '<u>%s</u>',
            'messageEntityBlockquote' => '<blockquote>%s</blockquote>',
            'messageEntityTextUrl' => '<a href="%s" target="_blank" rel="nofollow">%s</a>',
            'messageEntityMention' => '<a href="tg://resolve?domain=%s" rel="nofollow">%s</a>',
            'messageEntityUrl' => '<a href="%s" target="_blank" rel="nofollow">%s</a>',
        ];

        $entities = array_reverse($entities);
        foreach ($entities as $entity) {
            if (isset($html[$entity['_']])) {
                $text = static::mbSubstr($message, $entity['offset'], $entity['length']);

                if (in_array($entity['_'], ['messageEntityTextUrl', 'messageEntityMention', 'messageEntityUrl'])) {
                    $textFormate = sprintf($html[$entity['_']], $entity['url'] ?? $text, $text);
                } else {
                    $textFormate = sprintf($html[$entity['_']], $text);
                }

                $message = static::substringReplace($message, $textFormate, $entity['offset'], $entity['length']);
            }
        }
        $message = nl2br($message);
        return $message;
    }

    private static function substringReplace(string $original, string $replacement, int $position, int $length): string
    {
        $startString = static::mbSubstr($original, 0, $position);
        $endString = static::mbSubstr($original, $position + $length, static::mbStrlen($original));
        return $startString . $replacement . $endString;
    }

    /**
     * Пересылает сообщения без ссылки на оригинал
     *
     * @param array $data
     * <pre>
     * [
     *  'from_peer' => '',
     *  'to_peer' => '',
     *  'id' => [], //Id сообщения, или нескольких сообщений
     * ]
     * </pre>
     *
     * @return Promise
     */
    public function copyMessages(array $data): Promise
    {
        return call(
            function() use ($data) {
                $data = array_merge(
                    [
                        'from_peer' => '',
                        'to_peer' => '',
                        'id' => [],
                    ],
                    $data
                );

                $response = yield $this->madelineProto->channels->getMessages(
                    [
                        'channel' => $data['from_peer'],
                        'id' => $data['id'],
                    ]
                );
                $result = [];
                if (!$response || !is_array($response) || !array_key_exists('messages', $response)) {
                    return $result;
                }

                foreach ($response['messages'] as $message) {
                    usleep(random_int(300, 2000) * 1000);
                    $messageData = [
                        'message' => $message['message'] ?? '',
                        'peer' => $data['to_peer'],
                        'entities' => $message['entities'] ?? [],
                    ];
                    if (static::hasMedia($message, false)) {
                        $messageData['media'] = $message; //MadelineProto сама достанет все media из сообщения.
                        $result[] = yield $this->sendMedia($messageData);
                    } else {
                        $result[] = yield $this->sendMessage($messageData);
                    }
                }

                return $result;
            }
        );
    }

    /**
     * @param array $data
     * <pre>
     * [
     *  'peer' => '',
     *  'message' => '',      // Текст сообщения,
     *  'media' => [],      // MessageMedia, Update, Message or InputMedia
     *  'reply_to_msg_id' => 0,       // (optional)
     *  'parse_mode' => 'HTML',  // (optional)
     * ]
     * </pre>
     *
     * @return MadelineProto\updates|Promise
     */
    public function sendMedia(array $data): Promise
    {
        $data = array_merge(
            [
                'peer' => '',
                'message' => '',
                'media' => [],
                'reply_to_msg_id' => 0,
                'parse_mode' => 'HTML',
            ],
            $data
        );

        return $this->madelineProto->messages->sendMedia($data);
    }

    /**
     * @param array $data
     * <pre>
     * [
     *  'peer' => '',
     *  'message' => '',      // Текст сообщения
     *  'reply_to_msg_id' => 0,       // (optional)
     *  'parse_mode' => 'HTML',  // (optional)
     * ]
     * </pre>
     *
     * @return Promise|MadelineProto\updates
     */
    public function sendMessage(array $data)
    {
        $data = array_merge(
            [
                'peer' => '',
                'message' => '',
                'reply_to_msg_id' => 0,
                'parse_mode' => 'HTML',
            ],
            $data
        );

        return $this->madelineProto->messages->sendMessage($data);
    }

    /**
     * @param array $data
     * <pre>
     * [
     *  'folder_id' => 0, // Id папки (optional)
     *  'q'  => '',  //Поисковый запрос
     *  'offset_rate' => 0,   // (optional)
     *  'offset_peer' => null, // (optional)
     *  'offset_id' => 0,   // (optional)
     *  'limit' => 10,  // (optional)
     * ]
     * </pre>
     *
     * @return Promise
     */
    public function searchGlobal(array $data): Promise
    {
        $data = array_merge(
            [

                'q' => '',
                'offset_rate' => 0,
                'offset_id' => 0,
                'limit' => 10,
            ],
            $data
        );
        return $this->madelineProto->messages->searchGlobal($data);
    }

    /**
     * Загружает медиафайл из указанного сообщения в поток
     *
     * @param array $data
     *
     * @return Promise
     */
    public function getMedia(array $data): Promise
    {
        return call(
            function() use ($data) {
                $data = array_merge(
                    [
                        'peer' => '',
                        'id' => [0],
                        'message' => [],
                        'size_limit' => 0,
                    ],
                    $data
                );

                $message = $data['message'] ?: (yield $this->getMessages($data))['messages'][0] ?? null;
                if (!$message || $message['_'] === 'messageEmpty') {
                    throw new UnexpectedValueException('Empty message');
                }

                if (!static::hasMedia($message)) {
                    throw new UnexpectedValueException('Message has no media');
                }

                $info = yield $this->madelineProto->getDownloadInfo($message);

                if ($data['size_limit'] && $info['size'] > $data['size_limit']) {
                    throw new OutOfRangeException(
                        "Media exceeds size limit. Size: {$info['size']} bytes; limit: {$data['size_limit']} bytes"
                    );
                }

                return yield $this->downloadToResponse($info);
            }
        );
    }

    /**
     * Загружает превью медиафайла из указанного сообщения в поток
     *
     * @param array $data
     *
     * @return Promise
     */
    public function getMediaPreview(array $data): Promise
    {
        return call(
            function() use ($data) {
                $data = array_merge(
                    [
                        'peer' => '',
                        'id' => [0],
                        'message' => [],
                    ],
                    $data
                );

                $message = $data['message'] ?: (yield $this->getMessages($data))['messages'][0] ?? null;
                if (!$message || $message['_'] === 'messageEmpty') {
                    throw new UnexpectedValueException('Empty message');
                }

                if (!static::hasMedia($message)) {
                    throw new UnexpectedValueException('Message has no media');
                }

                $media = $message['media'][array_key_last($message['media'])];
                switch (true) {
                    case isset($media['sizes']):
                        $thumb = $media['sizes'][array_key_last($media['sizes'])];
                        break;
                    case isset($media['thumb']['size']):
                        $thumb = $media['thumb'];
                        break;
                    case !empty($media['thumbs']):
                        $thumb = $media['thumbs'][array_key_last($media['thumbs'])];
                        break;
                    case isset($media['photo']['sizes']):
                        $thumb = $media['photo']['sizes'][array_key_last($media['photo']['sizes'])];
                        break;
                    default:
                        throw new UnexpectedValueException('Message has no preview');

                }
                $info = yield $this->madelineProto->getDownloadInfo($thumb);

                //Фикс для LAYER 100+
                //TODO: Удалить, когда снова станет доступна загрузка photoSize
                if (isset($info['thumb_size'])) {
                    $infoFull = yield $this->madelineProto->getDownloadInfo($media);
                    $infoFull['InputFileLocation']['thumb_size'] = $info['thumb_size'];
                    $infoFull['size'] = $info['size'];
                    $infoFull['mime'] = $info['mime'];
                    $info = $infoFull;
                }

                return yield $this->downloadToResponse($info);
            }
        );
    }

    /**
     * @param array $data
     *
     * @return Promise
     */
    public function getMessages(array $data): Promise
    {
        return call(
            function() use ($data) {
                $peerInfo = yield $this->madelineProto->getInfo($data['peer']);
                if ($peerInfo['type'] === 'channel') {
                    $response = yield $this->madelineProto->channels->getMessages(
                        [
                            'channel' => $data['peer'],
                            'id' => (array) $data['id'],
                        ]
                    );
                } else {
                    $response = yield $this->madelineProto->messages->getMessages(['id' => (array) $data['id']]);
                }

                return $response;
            }
        );
    }

    /**
     * Download to Amp HTTP response.
     *
     * @param array $info
     *      Any downloadable array: message, media etc...
     *
     * @return Promise
     */
    public function downloadToResponse(array $info): Promise
    {
        return call(function() use($info) {
            if (empty($info['size'])) {
                $info = yield $this->madelineProto->getDownloadInfo($info);
            }
            $range = $this->getByteRange($this->request->getHeader('Range'));

            if ($range['end'] === -1) {
                $range['end'] = $info['size'] - 1;
            } else {
                $range['end'] = min($range['end'], $info['size'] - 1);
            }

            $stream = new IteratorStream(new Producer(function (callable $emit) use($info, $range) {
                yield $this->madelineProto->downloadToCallable($info, static function($payload) use($emit) {
                    yield $emit($payload);
                    return strlen($payload);
                }, null, false, $range['start'], $range['end'] + 1);
            }));

            $headers = [
                'Content-Type' => $info['mime'],
//            'Accept-Ranges' => 'bytes',
//            'Content-Transfer-Encoding'=> 'Binary',
            ];

            if ($range['start'] > 0 || $range['end'] < $info['size'] - 1) {
                $headers['Content-Length'] = ($range['end'] - $range['start'] + 1);
                $headers['Content-Range'] = "bytes {$range['start']}-{$range['end']}/{$info['size']}";
            } else {
                $headers['Content-Length'] = $info['size'];
            }

            return [
                'headers' => $headers,
                'stream' => $stream,
            ];
        });
    }

    public function setEventHandler(): void
    {
        $this->madelineProto->setEventHandler(EventHandler::class);
    }

    private function getByteRange(?string $header): array
    {
        $matches = [
            'start' => 0,
            'end' => -1
        ];
        if ($header) {
            preg_match("~bytes=(?'start'\d+)-(?'end'\d*)~", $header, $matches);
        }
        return [
            'start' => (int) $matches['start'],
            'end' =>  (int) $matches['end'] ?: -1
        ];
    }

}