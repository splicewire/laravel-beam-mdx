<?php

namespace Splicewire\Beam\Mdx\Lens;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Splicewire\Beam\Mdx\MdxError;

/** A source refusal with local codes; ordinary Laravel validation handling remains authoritative. */
class MdxValidationException extends ValidationException
{
    /** @var array<string, list<string>> */
    protected array $codes = [];

    /** @param array<string, scalar> $params */
    public static function for(string $field, MdxError $error, array $params = []): static
    {
        $exception = static::withMessages([$field => $error->message($params)]);
        $exception->codes = [$field => [$error->code()]];

        return $exception;
    }

    /** @return array<string, list<string>> */
    public function codes(): array
    {
        return $this->codes;
    }

    public function render(Request $request): ?JsonResponse
    {
        if ($this->codes === [] || ! $request->expectsJson()) {
            return null;
        }

        // Render the original parent type with the same validation state, then enrich its existing response.
        $original = new ValidationException($this->validator, $this->response, $this->errorBag);
        $original->status($this->status);
        $original->redirectTo($this->redirectTo);
        $response = app(ExceptionHandler::class)->render($request, $original);

        if (! $response instanceof JsonResponse) {
            return null;
        }

        $body = $response->getData(true);
        if (! is_array($body)) {
            return null;
        }
        if (! array_key_exists('codes', $body)) {
            $body['codes'] = $this->codes;
        }
        $response->setData($body);

        return $response;
    }
}
