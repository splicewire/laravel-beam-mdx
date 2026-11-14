<?php

namespace Splicewire\Beam\Mdx\Tests\Lens;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Splicewire\Beam\Mdx\Lens\NodeMdxLens;
use Splicewire\Beam\Mdx\Tests\TestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/** The real NodeMdxLens subprocess and Laravel rendering, in the original standalone package harness. */
final class LocalizationWireTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.debug' => false, 'app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->app->setLocale('en');
        $this->app->make('translator')->getLoader()->addPath(__DIR__.'/../Fixtures/lang');

        Route::post('/__mdx/lens/{mode}', function (string $mode): void {
            $this->invokeLens($mode);
        });
        Route::post('/__mdx/control/{mode}', function (string $mode): void {
            throw ValidationException::withMessages(['source' => $this->english($mode)]);
        });
        Route::middleware('web')->post('/__mdx/browser/{kind}', function (string $kind): void {
            if ($kind === 'control') {
                $exception = ValidationException::withMessages(['source' => $this->english('no-document')]);
            } else {
                $exception = $this->refusal('no-document');
            }
            $exception->errorBag('mdx')->redirectTo('/mdx-return');
            throw $exception;
        });
        Route::post('/__mdx/custom/{kind}', function (string $kind): void {
            $exception = $kind === 'control'
                ? ValidationException::withMessages(['source' => $this->english('no-document')])
                : $this->refusal('no-document');
            $exception->status(409);
            $exception->response = response()->json(
                ['message' => 'custom/source', 'errors' => ['source' => ['custom/source']]]
                    + ($kind === 'legacy' ? ['code' => 72, 'codes' => null] : []),
                409,
                ['X-Mdx-Control' => 'kept'],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
            throw $exception;
        });
    }

    public function test_the_real_process_refusals_preserve_full_english_laravel_json_and_add_codes(): void
    {
        $cases = [
            'no-document' => 'mdx_lens_no_document',
            'no-print' => 'mdx_lens_printed_nothing',
            'missing-script' => 'mdx_lens_script_missing',
            'stderr' => 'mdx_lens_failure_detail',
            'no-json' => 'mdx_lens_no_json',
        ];
        foreach ($cases as $mode => $code) {
            $control = $this->postJson('/__mdx/control/'.$mode);
            $response = $this->postJson('/__mdx/lens/'.$mode);
            $control->assertStatus(422);
            $response->assertStatus(422);
            $expected = $control->json();
            $expected['codes'] = ['source' => [$code]];
            $this->assertSame($expected, $response->json(), $mode);
            $this->assertSame($control->headers->get('Content-Type'), $response->headers->get('Content-Type'));
            $this->assertSame($control->headers->get('Cache-Control'), $response->headers->get('Cache-Control'));
        }
    }

    public function test_the_real_process_refusals_are_pseudo_marked_with_exact_raw_parameters(): void
    {
        $this->app->setLocale('en-XA');
        foreach (['no-document', 'no-print', 'missing-script', 'stderr', 'no-json'] as $mode) {
            $response = $this->postJson('/__mdx/lens/'.$mode);
            $response->assertStatus(422);
            $line = '[!! '.$this->english($mode).' !!]';
            $this->assertSame(['source' => [$line]], $response->json('errors'), $mode);
            $this->assertSame($line, $response->json('message'), $mode);
        }
    }

    public function test_empty_stderr_keeps_the_actual_process_failure_detail_byte_for_byte(): void
    {
        $process = new Process([PHP_BINARY, $this->worker()], null, null, json_encode([
            'op' => 'read', 'body' => 'empty-stderr', 'context' => (object) [], 'root' => base_path(),
        ], JSON_THROW_ON_ERROR), 60.0);
        try {
            $process->mustRun();
            $this->fail('The local worker must fail.');
        } catch (ProcessFailedException $failure) {
            $detail = $failure->getMessage();
        }

        $response = $this->postJson('/__mdx/lens/empty-stderr');
        $response->assertStatus(422);
        $this->assertSame(['source' => [$detail]], $response->json('errors'));
        $this->assertSame(['source' => ['mdx_lens_failure_detail']], $response->json('codes'));
        $this->app->setLocale('en-XA');
        $response = $this->postJson('/__mdx/lens/empty-stderr');
        $response->assertStatus(422);
        $this->assertSame(['source' => ['[!! '.$detail.' !!]']], $response->json('errors'));
    }

    public function test_parent_catches_and_non_json_bag_redirect_and_status_are_unchanged(): void
    {
        $exception = $this->refusal('no-document');
        $this->assertInstanceOf(ValidationException::class, $exception);
        $this->assertSame('the MDX lens returned no doc.', $exception->getMessage());
        $this->assertSame(['source' => ['the MDX lens returned no doc.']], $exception->errors());
        $this->assertSame(422, $exception->status);
        $this->assertSame('default', $exception->errorBag);
        $this->assertNull($exception->redirectTo);
        $this->assertTrue(method_exists($exception, 'render'));
        $this->assertNull($exception->render(Request::create('/editor', 'POST')));

        $control = $this->post('/__mdx/browser/control');
        $control->assertRedirect('/mdx-return');
        $control->assertSessionHasErrors(['source' => $this->english('no-document')], errorBag: 'mdx');
        $this->app->make('session')->flush();
        $response = $this->post('/__mdx/browser/lens');
        $response->assertRedirect('/mdx-return');
        $response->assertSessionHasErrors(['source' => $this->english('no-document')], errorBag: 'mdx');
        $this->assertSame($control->getStatusCode(), $response->getStatusCode());
    }

    public function test_existing_json_response_headers_status_and_encoding_flags_survive(): void
    {
        $control = $this->postJson('/__mdx/custom/control');
        $response = $this->postJson('/__mdx/custom/lens');
        $response->assertStatus(409);
        foreach (['Content-Type', 'Cache-Control', 'X-Mdx-Control'] as $header) {
            $this->assertSame($control->headers->get($header), $response->headers->get($header));
        }
        $this->assertSame($control->baseResponse->getEncodingOptions(), $response->baseResponse->getEncodingOptions());
        $expected = $control->json();
        $expected['codes'] = ['source' => ['mdx_lens_no_document']];
        $this->assertSame($expected, $response->json());
        $this->assertStringContainsString('custom/source', $response->getContent());
    }

    public function test_existing_code_members_and_types_are_never_replaced(): void
    {
        $response = $this->postJson('/__mdx/custom/legacy');
        $response->assertStatus(409);
        $this->assertSame([
            'message' => 'custom/source',
            'errors' => ['source' => ['custom/source']],
            'code' => 72,
            'codes' => null,
        ], $response->json());
    }

    public function test_the_native_namespace_resolves_every_local_case_without_beam(): void
    {
        $class = 'Splicewire\\Beam\\Mdx\\MdxError';
        $this->assertTrue(enum_exists($class), 'The standalone MDX error catalog must exist.');
        $this->assertFalse($this->app->bound('Splicewire\\Beam\\Localization\\TextNamespaces'));
        $hint = $this->app->make('translator')->getLoader()->namespaces()['beam-mdx'] ?? null;
        $this->assertIsString($hint);
        $this->assertSame(realpath(__DIR__.'/../../lang'), realpath($hint));
        $params = ['script' => '/raw/script.mjs', 'detail' => 'RAW :missing Ω'];
        $replace = [':script' => $params['script'], ':detail' => $params['detail']];
        $cases = $class::cases();
        $this->assertCount(5, $cases);
        $codes = [];
        foreach ($cases as $case) {
            $english = strtr($case->defaultMessage(), $replace);
            $this->assertSame($case->value, $case->code());
            $this->assertSame($english, $case->message($params, 'en'));
            $this->assertSame('[!! '.$english.' !!]', $case->message($params, 'en-XA'));
            $codes[] = $case->code();
        }
        $this->assertCount(5, array_unique($codes));
    }

    public function test_a_legacy_local_validation_exception_has_no_invented_codes(): void
    {
        $class = 'Splicewire\\Beam\\Mdx\\Lens\\MdxValidationException';
        $this->assertTrue(class_exists($class), 'The local validation subtype must exist.');
        $exception = $class::withMessages(['source' => 'raw custom detail']);
        $this->assertSame([], $exception->codes());
        $request = Request::create('/editor', 'POST', server: ['HTTP_ACCEPT' => 'application/json']);
        $this->assertNull($exception->render($request));
    }

    public function test_the_real_worker_success_shape_stays_unchanged(): void
    {
        $this->assertSame(['doc' => [], 'meta' => null, 'metaData' => null], $this->lens()->read('valid'));
    }

    private function invokeLens(string $mode): void
    {
        $lens = $mode === 'missing-script'
            ? new NodeMdxLens(binary: PHP_BINARY, script: $this->missingScript())
            : $this->lens();
        if ($mode === 'no-print') {
            $lens->print([['mode' => $mode]]);
        } else {
            $lens->read($mode);
        }
    }

    private function refusal(string $mode): ValidationException
    {
        try {
            $this->invokeLens($mode);
        } catch (ValidationException $exception) {
            return $exception;
        }
        $this->fail('The local lens fixture must reach its validation refusal.');
    }

    private function english(string $mode): string
    {
        return match ($mode) {
            'no-document' => 'the MDX lens returned no doc.',
            'no-print' => 'the MDX lens printed nothing.',
            'missing-script' => 'the MDX lens script is missing at ['.$this->missingScript().'].',
            'stderr' => 'RAW diagnostic :missing Ω /path?x=1',
            'no-json' => 'the MDX lens returned no JSON.',
        };
    }

    private function lens(): NodeMdxLens
    {
        return new NodeMdxLens(binary: PHP_BINARY, script: $this->worker());
    }

    private function worker(): string
    {
        return __DIR__.'/../Fixtures/lens-worker.php';
    }

    private function missingScript(): string
    {
        return __DIR__.'/../Fixtures/missing-mdx-worker.php';
    }
}
