<?php

namespace IbrahimKaya\VisitTracker\Tests;

use IbrahimKaya\VisitTracker\Jobs\ProcessVisitLog;
use IbrahimKaya\VisitTracker\Middleware\VisitTracker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use hisorange\BrowserDetect\Contracts\ParserInterface;
use hisorange\BrowserDetect\Contracts\ResultInterface;
use Mockery;

class VisitTrackerMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    protected function trackingConfig(array $overrides = []): void
    {
        config(array_merge([
            'visit-tracker.log_bots' => true,
            'visit-tracker.use_queue' => true,
            'visit-tracker.detailed_ip_info' => false,
            'visit-tracker.attribute_on_auth' => false,
            'visit-tracker.excluded_paths' => [],
            'visit-tracker.excluded_methods' => [],
            'visit-tracker.skip_ajax' => false,
            'visit-tracker.skip_prefetch' => false,
            'visit-tracker.sample_rate' => 1.0,
            'visit-tracker.dedupe_seconds' => 0,
        ], $overrides));
    }

    protected function runMiddleware(Request $request, VisitTracker $middleware)
    {
        $response = $middleware->handle($request, fn ($req) => response('ok'));
        $middleware->terminate($request, $response);

        return $response;
    }

    public function test_middleware_uses_container_bound_browser_detect_result_in_terminate(): void
    {
        Bus::fake();
        $this->trackingConfig();

        $result = Mockery::mock(ResultInterface::class);
        $result->shouldReceive('isBot')->twice()->andReturn(false);
        $result->shouldReceive('deviceType')->once()->andReturn('Desktop');
        $result->shouldReceive('browserName')->once()->andReturn('Chrome 120');
        $result->shouldReceive('platformName')->once()->andReturn('Windows 10');

        $parser = Mockery::mock(ParserInterface::class);
        $parser->shouldReceive('detect')->once()->andReturn($result);

        $this->app->instance('browser-detect', $parser);

        $request = Request::create('/home', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 TestAgent',
        ]);
        $request->setLaravelSession($this->app['session.store']);

        $middleware = new VisitTracker();
        $response = $middleware->handle($request, fn ($req) => response('ok'));

        $this->assertSame('ok', $response->getContent());
        Bus::assertNotDispatched(ProcessVisitLog::class);

        $middleware->terminate($request, $response);

        Bus::assertDispatched(ProcessVisitLog::class, function (ProcessVisitLog $job) {
            $visitData = (function () {
                return $this->visitData;
            })->call($job);

            return $visitData['device_type'] === 'Desktop'
                && $visitData['browser'] === 'Chrome 120'
                && $visitData['platform'] === 'Windows 10'
                && $visitData['is_bot'] === false
                && ($visitData['path'] ?? null) === '/home';
        });
    }

    public function test_cheap_bot_precheck_skips_detect_and_dispatch_when_log_bots_is_false(): void
    {
        Bus::fake();
        $this->trackingConfig([
            'visit-tracker.log_bots' => false,
        ]);

        $parser = Mockery::mock(ParserInterface::class);
        $parser->shouldNotReceive('detect');
        $this->app->instance('browser-detect', $parser);

        $request = Request::create('/home', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        ]);
        $request->setLaravelSession($this->app['session.store']);

        $middleware = new VisitTracker();
        $response = $this->runMiddleware($request, $middleware);

        $this->assertSame('ok', $response->getContent());
        Bus::assertNotDispatched(ProcessVisitLog::class);
    }

    public function test_middleware_skips_ajax_when_configured(): void
    {
        Bus::fake();
        $this->trackingConfig([
            'visit-tracker.skip_ajax' => true,
            'visit-tracker.log_bots' => true,
        ]);

        $parser = Mockery::mock(ParserInterface::class);
        $parser->shouldNotReceive('detect');
        $this->app->instance('browser-detect', $parser);

        $request = Request::create('/home', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 TestAgent',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $request->setLaravelSession($this->app['session.store']);

        $this->runMiddleware($request, new VisitTracker());

        Bus::assertNotDispatched(ProcessVisitLog::class);
    }

    public function test_middleware_skips_prefetch_when_configured(): void
    {
        Bus::fake();
        $this->trackingConfig([
            'visit-tracker.skip_prefetch' => true,
        ]);

        $parser = Mockery::mock(ParserInterface::class);
        $parser->shouldNotReceive('detect');
        $this->app->instance('browser-detect', $parser);

        $request = Request::create('/home', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 TestAgent',
            'HTTP_SEC_PURPOSE' => 'prefetch',
        ]);
        $request->setLaravelSession($this->app['session.store']);

        $this->runMiddleware($request, new VisitTracker());

        Bus::assertNotDispatched(ProcessVisitLog::class);
    }

    public function test_browser_detector_recovers_from_incomplete_cached_result(): void
    {
        Bus::fake();
        $this->trackingConfig();

        $result = Mockery::mock(ResultInterface::class);
        $result->shouldReceive('isBot')->twice()->andReturn(false);
        $result->shouldReceive('deviceType')->once()->andReturn('Desktop');
        $result->shouldReceive('browserName')->once()->andReturn('Chrome 120');
        $result->shouldReceive('platformName')->once()->andReturn('Windows 10');

        $parser = Mockery::mock(ParserInterface::class);
        $parser->shouldReceive('detect')
            ->once()
            ->andThrow(new \TypeError('Return value must be of type ResultInterface, __PHP_Incomplete_Class returned'));
        $parser->shouldReceive('parse')->once()->andReturn($result);

        // bind (not instance) so forgetInstance() during recovery still resolves this mock
        $this->app->bind('browser-detect', fn () => $parser);

        $request = Request::create('/home', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 TestAgent',
        ]);
        $request->setLaravelSession($this->app['session.store']);

        $this->runMiddleware($request, new VisitTracker());

        Bus::assertDispatched(ProcessVisitLog::class);
    }
}
