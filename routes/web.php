<?php

use App\Http\Controllers\ExamSessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ExamSessionController::class, 'home'])->name('home');
Route::get('/exam', [ExamSessionController::class, 'show'])->name('exam.show');
Route::get('/exam/released', [ExamSessionController::class, 'released'])->name('exam.released');
Route::post('/exam/out', [ExamSessionController::class, 'teacherOut'])->name('exam.out');
Route::match(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'], '/exam/proxy/{path?}', [ExamSessionController::class, 'proxy'])
    ->where('path', '.*')
    ->name('exam.proxy');
Route::post('/exam/violation', [ExamSessionController::class, 'violation'])->name('exam.violation');
Route::post('/exam/reset', [ExamSessionController::class, 'reset'])->name('exam.reset');
