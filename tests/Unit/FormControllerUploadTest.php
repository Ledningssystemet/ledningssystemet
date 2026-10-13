<?php

namespace Tests\Unit;

use Illuminate\Config\Repository;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Log;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Ledningssystemet\Ledningssystemet\Http\Controllers\FormController;
use Ledningssystemet\Ledningssystemet\Models\Form;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FormControllerUploadTest extends TestCase
{
    private array $logged = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->logged = [];
        $app = new Application(dirname(__DIR__, 2));
        $app->instance('translator', new Translator(new ArrayLoader(), 'en'));
        $app->instance('date', new \Illuminate\Support\DateFactory());
        $this->setConfig($app, 'https://proxy.test', 'secret-token');
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);

        $logger = new class($this) {
            public function __construct(private FormControllerUploadTest $test) {}

            public function error($message, array $context = []): void
            {
                $this->test->record($message, $context);
            }
        };
        Log::swap($logger);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Application::setInstance(null);
        parent::tearDown();
    }

    public function record($message, array $context): void
    {
        $this->logged[] = [$message, $context];
    }

    private function setConfig(Application $app, ?string $url, ?string $token): void
    {
        $app->instance('config', new Repository(['ledningssystemet' => ['exchangeproxy_url' => $url, 'exchangeproxy_token' => $token, 'company_name' => 'Acme']]));
    }

    private function controller(callable $proxy): object
    {
        $form = new Form();
        $form->id = 42;

        return new class($form, $proxy) extends FormController {
            public static $proxy;

            public function __construct(Form $form, callable $proxy)
            {
                parent::__construct($form);
                static::$proxy = $proxy;
            }

            protected static function exchangeProxyRequest($method, $url, $data = [], $headers = [])
            {
                return (static::$proxy)($method, $url, $data);
            }

            public function send(array $data): int
            {
                return $this->submitToProxy($data);
            }
        };
    }

    public function testSuccessfulUploadReturnsServerId(): void
    {
        $this->assertSame(7, $this->controller(fn () => ['id' => '7'])->send(['name' => 'x']));
        $this->assertSame([], $this->logged);
    }

    public function testMissingConfigurationAbortsWithoutCallingProxy(): void
    {
        $this->setConfig(Application::getInstance(), null, null);
        $called = false;
        $controller = $this->controller(function () use (&$called) {
            $called = true;

            return ['id' => 1];
        });

        try {
            $controller->send([]);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(503, $e->getStatusCode());
            $this->assertStringContainsString('not configured', $e->getMessage());
        }
        $this->assertFalse($called);
        $this->assertSame(42, $this->logged[0][1]['form_id']);
    }

    public function testProxyFailureIsSanitized(): void
    {
        $controller = $this->controller(function () {
            throw new \RuntimeException('cURL error 28: timeout calling https://proxy.test with ******');
        });

        try {
            $controller->send(['name' => 'confidential-payload']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(503, $e->getStatusCode());
            $this->assertStringNotContainsString('secret-token', $e->getMessage());
            $this->assertStringNotContainsString('cURL', $e->getMessage());
            $reference = $this->logged[0][1]['reference'];
            $this->assertStringContainsString($reference, $e->getMessage());
        }

        $encoded = json_encode($this->logged);
        $this->assertStringNotContainsString('secret-token', $encoded);
        $this->assertStringNotContainsString('confidential-payload', $encoded);
        $this->assertSame(42, $this->logged[0][1]['form_id']);
    }

    #[DataProvider('invalidResponses')]
    public function testInvalidResponseIdAborts(mixed $response): void
    {
        $controller = $this->controller(fn () => $response);

        try {
            $controller->send([]);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(502, $e->getStatusCode());
            $this->assertSame('invalid_proxy_response', $this->logged[0][1]['reason']);
        }
    }

    public static function invalidResponses(): array
    {
        return [
            'null' => [null],
            'empty array' => [[]],
            'missing id' => [['foo' => 1]],
            'null id' => [['id' => null]],
            'zero id' => [['id' => 0]],
            'non numeric id' => [['id' => 'abc']],
            'array id' => [['id' => [1]]],
        ];
    }

    private function draftForm(): Form
    {
        $form = new class extends Form {
            public bool $saved = false;

            public function getStateAttribute()
            {
                return 'draft';
            }

            public HasManyThrough $relationStub;

            public function int_relations(): HasManyThrough
            {
                return $this->relationStub;
            }

            public function save(array $options = [])
            {
                $this->saved = true;

                return true;
            }
        };
        $relations = $this->createMock(HasManyThrough::class);
        $relations->method('__call')->with('pluck', ['email'])->willReturn(collect(['a@example.test', 'a@example.test', '']));
        $form->relationStub = $relations;
        $form->id = 42;
        $form->name = 'Form';
        $form->formdata = json_encode(['form_items' => []]);

        return $form;
    }

    private function uploadWith(Form $form, callable $proxy): void
    {
        $controller = new class($form, $proxy) extends FormController {
            public static $proxy;

            public function __construct(Form $form, callable $proxy)
            {
                parent::__construct($form);
                static::$proxy = $proxy;
            }

            protected static function exchangeProxyRequest($method, $url, $data = [], $headers = [])
            {
                return (static::$proxy)($method, $url, $data);
            }
        };
        $controller->upload();
    }

    public function testFailedUploadDoesNotMarkFormAsUploaded(): void
    {
        $form = $this->draftForm();

        try {
            $this->uploadWith($form, fn () => throw new \RuntimeException('boom secret-token'));
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(503, $e->getStatusCode());
        }

        $this->assertFalse($form->saved);
        $this->assertNull($form->uploaded_at);
        $this->assertNull($form->server_form_id);
        $this->assertSame(42, $this->logged[0][1]['form_id']);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $this->logged[0][1]['reference']);
        $this->assertStringNotContainsString('secret-token', json_encode($this->logged));
    }

    public function testInvalidIdResponseDoesNotMarkFormAsUploaded(): void
    {
        $form = $this->draftForm();

        try {
            $this->uploadWith($form, fn () => ['id' => 'abc']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(502, $e->getStatusCode());
        }

        $this->assertFalse($form->saved);
        $this->assertNull($form->server_form_id);
    }

    public function testSuccessfulUploadMarksFormAsUploaded(): void
    {
        $form = $this->draftForm();
        $sent = null;
        $this->uploadWith($form, function ($method, $url, $data) use (&$sent) {
            $sent = $data;

            return ['id' => 9];
        });

        $this->assertTrue($form->saved);
        $this->assertSame(9, $form->server_form_id);
        $this->assertNotNull($form->uploaded_at);
        $this->assertSame([['email' => 'a@example.test']], $sent['form']['users']);
    }
}
