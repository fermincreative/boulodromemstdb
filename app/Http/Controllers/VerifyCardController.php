<?php

namespace App\Http\Controllers;

use App\Actions\Cards\VerifyCard;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class VerifyCardController extends Controller
{
    public function __invoke(Request $request, VerifyCard $verify): Response
    {
        $result = $verify->handle($request->only(['l', 'n', 'j', 'c']));

        return response()
            ->view('cards.verify', ['result' => $result, 'checkedAt' => now()])
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'no-store, private');
    }
}
