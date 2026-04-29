<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExamSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_loads(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Exam Browser')
            ->assertSee('Out')
            ->assertSee('elsph.permataharapanku.sch.id');
    }

    public function test_exam_session_starts_and_blocks_after_three_violations(): void
    {
        $this->get(route('home'))->assertOk();

        $this->get(route('exam.show'))
            ->assertOk()
            ->assertSee('Exam Browser')
            ->assertSee('elsph.permataharapanku.sch.id');

        $this->postJson(route('exam.violation'), [
            'type' => 'tab-switch',
            'detail' => 'left the page',
        ])->assertOk()->assertJson([
            'violations' => 1,
            'blocked' => false,
        ]);

        $this->postJson(route('exam.violation'), [
            'type' => 'shortcut',
            'detail' => 'devtools shortcut',
        ])->assertOk()->assertJson([
            'violations' => 2,
            'blocked' => false,
        ]);

        $this->postJson(route('exam.violation'), [
            'type' => 'paste',
            'detail' => 'paste attempt',
        ])->assertOk()->assertJson([
            'violations' => 3,
            'blocked' => true,
            'blocked_reason' => 'Paste attempt',
        ]);

        $this->get(route('exam.show'))
            ->assertOk()
            ->assertSee('Locked');
    }

    public function test_proxy_route_rewrites_html_to_stay_on_the_same_origin(): void
    {
        Http::fake([
            'elsph.permataharapanku.sch.id*' => Http::response(
                '<html><head><title>Exam</title></head><body><a href="/next">Next</a></body></html>',
                200,
                ['Content-Type' => 'text/html; charset=UTF-8']
            ),
        ]);

        $this->withSession([
            'exam_session' => [
                'id' => 'test-session',
                'student_name' => 'Jane Doe',
                'exam_url' => 'https://elsph.permataharapanku.sch.id/',
                'exam_title' => 'Math Midterm',
                'approved_exam_host' => 'elsph.permataharapanku.sch.id',
                'violations' => 0,
                'blocked' => false,
                'blocked_reason' => null,
                'started_at' => now()->toIso8601String(),
                'last_violation_at' => null,
                'events' => [],
            ],
        ])->get(route('exam.proxy'))
            ->assertOk()
            ->assertSee('<base href="', false)
            ->assertSee('exam/proxy');
    }

    public function test_copy_violation_locks_the_client_immediately_and_persists(): void
    {
        $this->get(route('home'));

        $this->postJson(route('exam.violation'), [
            'type' => 'copy',
            'detail' => 'clipboard attempt',
        ])->assertOk()->assertJson([
            'violations' => 1,
            'blocked' => true,
            'blocked_reason' => 'Copy attempt',
        ]);

        $this->get(route('exam.show'))
            ->assertOk()
            ->assertSee('Locked')
            ->assertSee('Copy attempt');
    }

    public function test_teacher_out_code_releases_the_session(): void
    {
        config(['exam.teacher_out_code' => 'TEACHER123']);

        $this->get(route('home'));

        $this->post(route('exam.out'), [
            'teacher_code' => 'TEACHER123',
        ])->assertRedirect(route('exam.released'));

        $this->get(route('exam.released'))
            ->assertOk()
            ->assertSee('Session released');
    }
}
