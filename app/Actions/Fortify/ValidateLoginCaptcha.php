<?php

namespace App\Actions\Fortify;

use App\Support\LoginCaptcha;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ValidateLoginCaptcha
{
    public function __construct(private readonly LoginCaptcha $captcha) {}

    public function __invoke(Request $request, Closure $next): mixed
    {
        $answer = $request->string('captcha_answer')->toString();

        if (! $this->captcha->check($request, $answer)) {
            $this->captcha->generate($request);

            throw ValidationException::withMessages([
                'captcha_answer' => '验证码不正确，请重新输入。',
            ]);
        }

        $this->captcha->clear($request);

        return $next($request);
    }
}
