<?php

namespace App\Support;

use Illuminate\Http\Request;

class LoginCaptcha
{
    public const SessionAnswerKey = 'auc.login_captcha.answer';

    public const SessionQuestionKey = 'auc.login_captcha.question';

    /**
     * @return array{question: string}
     */
    public function generate(Request $request): array
    {
        $left = random_int(2, 9);
        $right = random_int(1, 9);
        $operator = random_int(0, 1) === 0 ? '+' : '-';

        if ($operator === '-' && $left < $right) {
            [$left, $right] = [$right, $left];
        }

        $answer = $operator === '+' ? $left + $right : $left - $right;
        $question = "{$left} {$operator} {$right} = ?";

        $request->session()->put(self::SessionQuestionKey, $question);
        $request->session()->put(self::SessionAnswerKey, (string) $answer);

        return ['question' => $question];
    }

    public function check(Request $request, ?string $answer): bool
    {
        $expected = $request->session()->get(self::SessionAnswerKey);

        return $expected !== null && hash_equals((string) $expected, trim((string) $answer));
    }

    public function clear(Request $request): void
    {
        $request->session()->forget([
            self::SessionQuestionKey,
            self::SessionAnswerKey,
        ]);
    }
}
