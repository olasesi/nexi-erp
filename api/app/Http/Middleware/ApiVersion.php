<?php

namespace App\Http\Middleware;

use App\Support\ApiVersions;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves which API version a request targets and advertises the lifecycle of
 * that version on the response. A version can be selected through the
 * /api/{version}/... path segment, the Accept media type or the version
 * header; an unknown version is refused and a version that reached its sunset
 * date answers with 410 Gone.
 */
class ApiVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        $declared = $this->fromPath($request);
        $requested = $declared ?? $this->fromAccept($request) ?? $this->fromHeader($request);

        if ($declared !== null && ! ApiVersions::supports($declared)) {
            return $this->refuse($declared, Response::HTTP_NOT_FOUND, sprintf('API version "%s" does not exist.', $declared));
        }

        if ($requested !== null && ! ApiVersions::supports($requested)) {
            return $this->refuse($requested, Response::HTTP_BAD_REQUEST, sprintf('API version "%s" is not supported.', $requested));
        }

        $version = $requested ?? ApiVersions::default();
        $request->attributes->set('api_version', $version);

        if (ApiVersions::isSunset($version)) {
            $sunset = ApiVersions::sunsetAt($version);

            return $this->refuse(
                $version,
                Response::HTTP_GONE,
                sprintf('API version "%s" was retired on %s.', $version, $sunset?->toDateString() ?? 'an earlier date')
            );
        }

        $response = $next($request);
        $this->advertise($request, $response, $version);

        return $response;
    }

    /**
     * Stamp the served version and, for a deprecated one, the RFC 8594
     * Deprecation/Sunset headers plus a Link to its replacement.
     */
    private function advertise(Request $request, Response $response, string $version): void
    {
        $response->headers->set(ApiVersions::header(), $version);

        if (! ApiVersions::isDeprecated($version)) {
            return;
        }

        $meta = ApiVersions::describe($version);
        $response->headers->set('Deprecation', 'true');

        $sunset = $meta['sunset_at'] ?? null;

        if (is_string($sunset)) {
            $response->headers->set('Sunset', Carbon::parse($sunset)->toRfc7231String());
        }

        $replacement = $meta['replaced_by'] ?? null;

        if (is_string($replacement) && ApiVersions::supports($replacement)) {
            $response->headers->set('Link', sprintf(
                '<%s>; rel="deprecation"; type="%s"',
                $this->pathFor($request, $replacement),
                ApiVersions::mediaType($replacement)
            ));
        }

        $notice = $meta['notice'] ?? sprintf('API version %s is deprecated.', $version);
        $response->headers->set('Warning', sprintf('299 - "%s"', str_replace('"', "'", (string) $notice)));
    }

    /**
     * Same path, one version further along.
     */
    private function pathFor(Request $request, string $version): string
    {
        $segments = explode('/', trim($request->path(), '/'));
        $index = $segments[0] === ApiVersions::pathPrefix() ? 1 : 0;

        if (isset($segments[$index])) {
            $segments[$index] = $version;
        }

        return '/'.implode('/', $segments);
    }

    private function fromPath(Request $request): ?string
    {
        $segments = explode('/', trim($request->path(), '/'));
        $index = $segments[0] === ApiVersions::pathPrefix() ? 1 : 0;
        $candidate = $segments[$index] ?? null;

        return is_string($candidate) && preg_match('/^v\d+$/i', $candidate) === 1
            ? strtolower($candidate)
            : null;
    }

    private function fromAccept(Request $request): ?string
    {
        $accept = (string) $request->headers->get('Accept', '');
        $prefix = ApiVersions::mediaPrefix();

        if (preg_match('/'.preg_quote($prefix, '/').'\.(v\d+)/i', $accept, $matches) !== 1) {
            return null;
        }

        return strtolower($matches[1]);
    }

    private function fromHeader(Request $request): ?string
    {
        $value = trim((string) $request->headers->get(ApiVersions::header(), ''));

        return $value === '' ? null : strtolower($value);
    }

    private function refuse(string $version, int $status, string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'requested_version' => $version,
            'supported_versions' => ApiVersions::supported(),
        ], $status);
    }
}
