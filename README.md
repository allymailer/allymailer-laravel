# AllyMailer Laravel 2.0.0

Web API 1.90

Покроковий посібник: [Надсилання листів на PHP](https://docs.allymailer.com.ua/ua/send-with-php) · Документація для LLM: [llms.txt](https://docs.allymailer.com.ua/llms.txt) · [Довідник API](https://docs.allymailer.com.ua/ua/web-api-ref)

Інтеграція [PHP SDK AllyMailer](https://github.com/allymailer/allymailer-php-sdk) (`allymailer/allymailer-sdk`) з Laravel: клієнт API в service container (+ facade), ендпоінт вебхуків із перевіркою підпису, транспорт Laravel Mail, що надсилає листи через `email/send`.

## Встановлення

```bash
composer require allymailer/allymailer-laravel
php artisan vendor:publish --tag=allymailer-config  # config/allymailer.php
```

Вимоги: PHP 8.1+, Laravel 10–12.

## Отримайте API-ключ

1. Зареєструйтеся на [cp.allymailer.com](https://cp.allymailer.com).
2. Скопіюйте API-ключ у [панелі керування](https://cp.allymailer.com).
3. Покладіть його у змінну оточення:

```bash
export ALLYMAILER_API_KEY="your-api-key"
```

Пакет бере його з `ALLYMAILER_API_KEY` (див. налаштування нижче).

> **Лише на сервері.** Використовуйте цей пакет у серверному коді: API-маршрути, серверні функції, воркери.
> Ніколи не передавайте API-ключ у браузер чи мобільний застосунок і не комітьте його.

## Налаштування

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

## Перший лист — без налаштування DNS

Кожен обліковий запис може одразу надсилати тестові листи із **sandbox-домену**: він уже підтверджений, DKIM і SPF налаштовані.
Листи доставляються лише на підтверджені вами адреси.

У прикладах — клієнт API з пакета (див. налаштування вище).

**1. Отримайте sandbox-домен** (`domain/get-sandbox-domain`). Перший виклик створює домен, наступні повертають той самий.

```php
use AllyMailer\AllyMailerClient;

$client = app(AllyMailerClient::class); // or injection, or the AllyMailer facade
$response = $client->domain()->getSandboxDomain();
echo $response->domain;
```

**2. Підтвердіть свою скриньку** (`confirmed-email/create`). AllyMailer надішле лист із підтвердженням; перейдіть за посиланням із нього протягом 48 годин.

```php
use AllyMailer\AllyMailerClient;
use AllyMailer\Api\ConfirmedEmail\Model\ConfirmedEmailRequest;

$client = app(AllyMailerClient::class); // or injection, or the AllyMailer facade
$response = $client->confirmedEmail()->create(new ConfirmedEmailRequest(
    email: 'you@example.com',
));
echo $response->status;
```

Статус перевірте через `confirmed-email/list` — у вашої адреси має бути `"status": "confirmed"`.

**3. Надішліть лист** (`email/send`) з будь-якої адреси sandbox-домену на підтверджену адресу.

```php
use AllyMailer\AllyMailerClient;
use AllyMailer\Api\Email\Model\EmailSendRequest;
use AllyMailer\Api\Email\Model\MessageObject;
use AllyMailer\Api\Email\Model\MessageObjectRecipientsItem;
use AllyMailer\Common\Model\BodyObject;

$client = app(AllyMailerClient::class); // or injection, or the AllyMailer facade
$response = $client->email()->send(new EmailSendRequest(
    message: new MessageObject(
        recipients: [
            new MessageObjectRecipientsItem(
                email: 'you@example.com',
            ),
        ],
        body: new BodyObject(
            html: '<p>Працює!</p>',
        ),
        subject: 'Привіт від AllyMailer',
        fromEmail: 'noreply@<sandbox-domain>',
    ),
));
echo $response->jobId;
```

### Що повертається

```json
{
  "status": "success",
  "job_id": "1ZymBc-00041N-9X",
  "emails": [
    "you@example.com"
  ]
}
```

У разі помилки API повертає `status: "error"`, `message` і числовий `code`:

```json
{
  "status": "error",
  "code": 102,
  "message": "Error ID:83274F98-C7D5-11F1-B221-6214FF8707B1. Can not decode key wrongkey"
}
```

### Те саме через curl

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

## Надсилання через API

Окрім Laravel Mail, листи можна надсилати напряму через клієнт API. Клієнт викликає [email/send](https://docs.allymailer.com.ua/ua/web-api-ref#email-send) з усіма його параметрами: шаблони, підстановки, метадані, трекінг. Поля запиту та інші приклади — у [документації PHP SDK](https://github.com/allymailer/allymailer-php-sdk/blob/HEAD/docs/Email.md#send).

```php
use AllyMailer\AllyMailerClient;
use AllyMailer\Api\Email\Model\EmailSendRequest;
use AllyMailer\Api\Email\Model\MessageObject;
use AllyMailer\Api\Email\Model\MessageObjectRecipientsItem;
use AllyMailer\Common\Model\BodyObject;

$client = app(AllyMailerClient::class); // or injection, or the AllyMailer facade
$response = $client->email()->send(new EmailSendRequest(
    message: new MessageObject(
        recipients: [
            new MessageObjectRecipientsItem(
                email: 'user@example.com',
                substitutions: [
                    'CustomerId' => '12452',
                    'to_name' => 'John Smith',
                ],
            ),
        ],
        body: new BodyObject(
            html: '<b>Hello, {{to_name}}</b>',
            plaintext: 'Hello, {{to_name}}',
        ),
        subject: 'AllyMailer test email ',
        fromEmail: 'email@example.com',
        fromName: 'John Smith',
    ),
));
echo $response->jobId;
```

## Обмеження sandbox

- Надсилання лише на підтверджені адреси: до 10 на обліковий запис.
- До 100 листів на день.
- Sandbox-домен спливає через 31 день; знову викличте `domain/get-sandbox-domain`, щоб отримати новий.
- Працює лише з API-ключем облікового запису, не з ключем проєкту.
- Не показується в `domain/list`; інші методи `domain/*` до нього не застосовуються.

Sandbox — для розробки та тестування. Не використовуйте sandbox-домен як відправника на проді.

## Перед продом: підтвердіть свій домен

1. `domain/get-dns-records` — повертає значення для DNS-записів.
2. Додайте ці записи у свого DNS-провайдера (їх також показує сторінка домену в панелі керування):

   | Тип | Хост | Значення |
   |---|---|---|
   | TXT | `@` | значення `verification-record` (`allymailer-validate-hash=…`) |
   | TXT | `<селектор>._domainkey` (селектор — на сторінці домену в панелі керування, наприклад `us`) | `v=DKIM1; k=rsa; p=<dkim>` |
   | TXT | `@` | `v=spf1 include:spf.allymailer.com ~all` |
   | CNAME | `_dmarc` | значення `dmarc` |

3. `domain/validate-verification-record`, потім `domain/validate-dkim`.
4. `domain/list` — дочекайтеся, поки запис підтвердження буде підтверджено, а DKIM — активний.
5. Беріть `from_email` з конфігурації, щоб перехід із sandbox не потребував змін у коді.

## Надійне надсилання

- **Повтори:** ставте `message.idempotence_key` у надсиланнях, які можуть повторюватися, щоб повторний запит не надіслав лист двічі.
- **Ліміти:** до 500 отримувачів і 10 МБ на запит; кожне вкладення до 7 МБ.
- **Тіло:** завжди передавайте `body.html` або `body.plaintext`.
- **Статус доставки:** зареєструйте вебхук (`webhook/set`) зі швидким ідемпотентним HTTPS-обробником замість опитування.
- **Типові налаштування:** не прибирайте стандартний блок відписки та трекінг; змінити їх можна через підтримку AllyMailer.
- **Помилки:** повторюйте `429` і `5xx` з backoff; інші помилки без змін не повторюйте.

## Посилання

- [Надсилання листів на PHP](https://docs.allymailer.com.ua/ua/send-with-php) та інші [посібники з інтеграції](https://docs.allymailer.com.ua/ua/integrations/)
- [Довідник Web API](https://docs.allymailer.com.ua/ua/web-api-ref)
- [llms.txt](https://docs.allymailer.com.ua/llms.txt) для AI-асистентів
- [Підтримка](https://cp.allymailer.com/ua/support)

## Для розробників пакета

Див. [AGENTS.md](AGENTS.md): як цей пакет генерується з OpenAPI-специфікації, збирається, перевіряється та випускається.
