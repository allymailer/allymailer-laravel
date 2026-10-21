# AllyMailer Laravel

[![Laravel](https://img.shields.io/packagist/v/allymailer/allymailer-laravel)](https://packagist.org/packages/allymailer/allymailer-laravel) [![License: MIT](https://img.shields.io/badge/license-MIT-blue)](LICENSE)

Інтеграція [PHP SDK AllyMailer](https://github.com/allymailer/allymailer-php-sdk) (`allymailer/allymailer-sdk`) з Laravel: клієнт API в service container (+ facade), ендпоінт вебхуків із перевіркою підпису, транспорт Laravel Mail, що надсилає листи через `email/send`.

[Довідник API](https://docs.allymailer.com.ua/ua/web-api-ref) · Документація для LLM: [llms.txt](https://docs.allymailer.com.ua/llms.txt)

## Встановлення

```bash
composer require allymailer/allymailer-laravel
php artisan vendor:publish --tag=allymailer-config  # config/allymailer.php
```

Вимоги: PHP 8.1+, Laravel 10–12.

## Налаштування

Отримайте API-ключ у [панелі керування](https://cp.allymailer.com) і зберігайте його у змінній оточення, а не в коді:

```bash
export ALLYMAILER_API_KEY="your-api-key"
```

За замовчуванням налаштування беруться зі змінних оточення:

```bash
# .env
ALLYMAILER_API_KEY=YOUR_API_KEY
ALLYMAILER_HOST=cp.allymailer.com
MAIL_MAILER=allymailer

# config/mail.php, 'mailers':
#   'allymailer' => ['transport' => 'allymailer'],
```

| Параметр | Змінна оточення | За замовчуванням | Опис |
|---|---|---|---|
| `allymailer.api_key` | `ALLYMAILER_API_KEY` |  | API-ключ користувача або проєкту (обов'язковий). |
| `allymailer.host` | `ALLYMAILER_HOST` | `cp.allymailer.com` | Хост API (датацентр: `cp.allymailer.com`) або повний базовий URL. |
| `allymailer.timeout` | `ALLYMAILER_TIMEOUT` | `30` | Таймаут запиту, секунди. |
| `allymailer.webhook.key` | `ALLYMAILER_WEBHOOK_KEY` |  | API-ключ, яким підписані вебхуки (ключ користувача/проєкту вебхука), якщо він відрізняється від API-ключа. |
| `allymailer.webhook.path` | `ALLYMAILER_WEBHOOK_PATH` | `/allymailer/webhook` | Шлях ендпоінта вебхуків. |

Маршрут `allymailer.webhook` (`/allymailer/webhook`) не входить до групи middleware `web`, виняток CSRF для нього не потрібен; `allymailer.webhook.enabled = false` вимикає його.

## Надсилання листів

Листи Laravel Mail надсилаються через [email/send](https://docs.allymailer.com.ua/ua/web-api-ref#email-send): відправник, to/cc/bcc, reply-to, тема, HTML і текстова частини, вкладення, inline-зображення та заголовки `X-*`.

```php
use Illuminate\Support\Facades\Mail;

Mail::to('user@example.com')->send(new OrderShipped($order));

// Mailable: tags and metadata go to the API as well
public function envelope(): Envelope
{
    return new Envelope(subject: 'Order shipped', tags: ['orders'], metadata: ['order_id' => '42']);
}
```

Домену ще немає? Надсилайте з [тестового sandbox-домену](#без-свого-домену-тестовий-sandbox-домен).

## Вебхуки

Ендпоінт відповідає 200 OK на `GET` (перевірка URL під час [webhook/set](https://docs.allymailer.com.ua/ua/web-api-ref#webhook-set)) і приймає `POST`-сповіщення: перевіряє поле `auth` (MD5 тіла запиту, у якому значення `auth` замінено на API-ключ), на неправильний підпис відповідає 401, а сповіщення і кожну його подію передає застосунку.

```php
use Illuminate\Support\Facades\Event;
use AllyMailer\Wrapper\Events\WebhookEventReceived;

// AppServiceProvider::boot() or a listener class
Event::listen(function (WebhookEventReceived $e) {
    $data = $e->event->eventData;
    logger()->info('AllyMailer event', ['name' => ($n = $e->event->eventName) instanceof \BackedEnum ? $n->value : $n, 'email' => $data?->email,
        'status' => ($s = $data?->status) instanceof \BackedEnum ? $s->value : $s]);
});
```

Зареєструйте URL ендпоінта методом `webhook/set`, наприклад `https://example.com/allymailer/webhook`.

> **Відповідайте 200 протягом 3 секунд**, а події обробляйте у фоні (через чергу): інакше AllyMailer повторює запит кожні 10 хвилин протягом доби (зростає `retry_count`), а обробник, на якому за добу не пройшов жоден запит хоча б для 10 подій, вимикається. Обробник має витримувати **щонайменше 5 паралельних запитів до 100 подій у кожному**; повтор надсилає ті самі події ще раз — прибирайте дублікати.

## Клієнт API

Усі розділи API доступні через клієнт, див. [документацію SDK](https://github.com/allymailer/allymailer-php-sdk).

```php
use AllyMailer\AllyMailerClient;
use AllyMailer\Wrapper\Facades\AllyMailer;

// facade
$info = AllyMailer::system()->info();

// or injection
public function usage(AllyMailerClient $client): array
{
    return ['emails_sent' => $client->system()->info()->accounting?->emailsSent];
}
```

### Надсилання через API

Окрім Laravel Mail, листи можна надсилати напряму через клієнт API. Клієнт викликає [email/send](https://docs.allymailer.com.ua/ua/web-api-ref#email-send) з усіма його параметрами: шаблони, підстановки, метадані, трекінг. Поля запиту та інші приклади — у [документації PHP SDK](https://github.com/allymailer/allymailer-php-sdk/blob/HEAD/docs/Email.md#send).

```php
use AllyMailer\AllyMailerClient;
use AllyMailer\Api\Email\Model\EmailSendRequest;
use AllyMailer\Api\Email\Model\MessageObject;
use AllyMailer\Api\Email\Model\MessageObjectRecipientsItem as Recipient;
use AllyMailer\Common\Model\BodyObject as Body;
use Illuminate\Support\Facades\Route;

Route::post('/send', function (AllyMailerClient $client) {
    $response = $client->email()->send(new EmailSendRequest(new MessageObject(
        recipients: [Recipient::fromAddress('Олексій <alex@example.com>')],
        body: Body::html('<p>Вітаємо, {{to_name}}! Ваше замовлення надіслано.</p>'),
        subject: 'Ваше замовлення',
        fromEmail: 'shop@example.com',
        fromName: 'Магазин',
    )));

    return ['job_id' => $response->jobId];
});
```

## Без свого домену: тестовий sandbox-домен

Поки ваш домен не підтверджено, листи можна надсилати із **sandbox-домену** — тестового домену, який є в кожного облікового запису: він уже підтверджений, DKIM і SPF налаштовані. Листи з нього доставляються лише на підтверджені вами адреси (посилання для підтвердження приходить листом і діє 48 годин), до 100 на день. Через 31 день після створення sandbox-домен видаляється, але його можна отримати знову: наступний виклик `domain/get-sandbox-domain` створить новий (з іншою назвою, тому беріть її з відповіді, а не прописуйте в коді).

У прикладах — клієнт API з пакета (див. налаштування вище).

```php
use AllyMailer\AllyMailerClient;
use AllyMailer\Api\ConfirmedEmail\Model\ConfirmedEmailRequest;
use AllyMailer\Api\Email\Model\EmailSendRequest;
use AllyMailer\Api\Email\Model\MessageObject;
use AllyMailer\Api\Email\Model\MessageObjectRecipientsItem as Recipient;
use AllyMailer\Common\Model\BodyObject as Body;
use Illuminate\Support\Facades\Route;

Route::post('/sandbox', function (AllyMailerClient $client) {
    // 1. Ваш sandbox-домен: створюється під час першого виклику, далі повертається той самий
    $domain = $client->domain()->getSandboxDomain()->domain;

    // 2. Підтвердіть свою скриньку: AllyMailer надішле посилання, перейдіть за ним протягом 48 годин
    $client->confirmedEmail()->create(new ConfirmedEmailRequest(email: 'you@example.com'));

    // 3. Надішліть із будь-якої адреси sandbox-домену на підтверджену адресу
    $client->email()->send(new EmailSendRequest(new MessageObject(
        recipients: [Recipient::fromAddress('you@example.com')],
        body: Body::html('<p>Працює!</p>'),
        subject: 'Привіт від AllyMailer',
        fromEmail: "noreply@{$domain}",
    )));

    return ['domain' => $domain];
});
```

<details>
<summary>Обмеження sandbox</summary>

- Надсилання лише на підтверджені адреси: до 10 на обліковий запис.
- До 100 листів на день.
- Sandbox-домен спливає через 31 день; знову викличте `domain/get-sandbox-domain`, щоб отримати новий.
- Працює лише з API-ключем облікового запису, не з ключем проєкту.
- Не показується в `domain/list`; інші методи `domain/*` до нього не застосовуються.

Sandbox — для розробки та тестування. Не використовуйте sandbox-домен як відправника на проді.

</details>

<details>
<summary>Те саме через curl</summary>

```bash
BASE=https://cp.allymailer.com/ua/transactional/api/v1

curl -s -X POST $BASE/domain/get-sandbox-domain.json \
  -H "Content-Type: application/json" -H "X-API-KEY: $ALLYMAILER_API_KEY" -d '{}'

curl -s -X POST $BASE/confirmed-email/create.json \
  -H "Content-Type: application/json" -H "X-API-KEY: $ALLYMAILER_API_KEY" \
  -d '{"email": "you@example.com"}'

curl -s -X POST $BASE/email/send.json \
  -H "Content-Type: application/json" -H "X-API-KEY: $ALLYMAILER_API_KEY" \
  -d '{
        "message": {
          "recipients": [
            {
              "email": "you@example.com"
            }
          ],
          "from_email": "noreply@<sandbox-domain>",
          "subject": "Привіт від AllyMailer",
          "body": {
            "html": "<p>Працює!</p>"
          }
        }
      }'
```

</details>

## Запуск у продакшен

**1. Підтвердіть домен надсилання.** `domain/get-dns-records` повертає запис підтвердження, DKIM-ключ і адресу для DMARC; додайте записи у свого DNS-провайдера, потім `domain/validate-verification-record` і `domain/validate-dkim`.

| Тип | Хост | Значення | Звідки взяти |
|---|---|---|---|
| TXT | `@` | `allymailer-validate-hash=…` | поле `verification-record` відповіді, як є |
| TXT | `<селектор>._domainkey` (селектор — на сторінці домену в панелі керування, наприклад `us`) | `v=DKIM1; k=rsa; p=<dkim>` | поле `dkim` відповіді після `v=DKIM1; k=rsa; p=` |
| TXT | `@` | `v=spf1 include:spf.allymailer.com ~all` | у відповіді немає; якщо SPF-запис уже є, додайте include до нього |
| CNAME | `_dmarc` | `<домен>.dmarc.…` | поле `dmarc` відповіді (DMARC-політику публікує AllyMailer); якщо в домену вже є свій TXT-запис DMARC, залиште його |

**2. Надійне надсилання.**

| | |
|---|---|
| Повтори | `429` і `5xx` з backoff і тим самим `idempotence_key`; інші помилки виправляйте, а не повторюйте |
| Ліміти | 500 одержувачів і 10 МБ на запит разом із вкладеннями: у base64 файл стає приблизно на третину більшим |
| Тіло | завжди `html` або `plaintext` (або із шаблону) |
| Статус доставки | вебхук, а не опитування |
| Черга | надсилайте з фонового завдання, а не всередині веб-запиту |
| Налаштування | `from_email` з конфігурації: перехід із sandbox на продакшен без змін у коді |
| Типові налаштування | не прибирайте блок відписки та трекінг; змінити їх можна через підтримку AllyMailer |

## Допомога

- [PHP SDK](https://github.com/allymailer/allymailer-php-sdk)
- Для ШІ-агентів: [llms.txt](https://docs.allymailer.com.ua/llms.txt), скіли: `npx skills add https://github.com/allymailer/allymailer-skills.git`
- [Підтримка](https://cp.allymailer.com/ua/support) · [Задачі](https://github.com/allymailer/allymailer-laravel/issues)
- Розробникам: [AGENTS.md](AGENTS.md) — пакет генерується з OpenAPI-специфікації.
