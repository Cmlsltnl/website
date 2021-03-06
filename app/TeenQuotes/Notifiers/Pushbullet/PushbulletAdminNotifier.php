<?php

/*
 * This file is part of the Teen Quotes website.
 *
 * (c) Antoine Augusti <antoine.augusti@teen-quotes.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TeenQuotes\Notifiers\Pushbullet;

use Illuminate\Translation\Translator as Lang;
use Pushbullet;
use TeenQuotes\Notifiers\AdminNotifier;

class PushbulletAdminNotifier implements AdminNotifier
{
    /**
     * The API key of Pushbullet.
     *
     * @var string
     */
    private $apiKey;

    /**
     * The ID of the device we will send the notification on.
     *
     * @var string
     */
    private $deviceIden;

    /**
     * @var \Illuminate\Translation\Translator
     */
    private $lang;

    public function __construct(Lang $lang, $apiKey, $deviceIden)
    {
        $this->apiKey     = $apiKey;
        $this->deviceIden = $deviceIden;
        $this->lang       = $lang;
    }

    /**
     * Notify an administrator about an event.
     *
     * @param string $message
     */
    public function notify($message)
    {
        $title = $this->lang->get('layout.nameWebsite');

        $p = new Pushbullet($this->apiKey);

        $p->pushNote($this->deviceIden, $title, $message);
    }
}
