<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class PasswordRecoveryController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:254']]);
        $email = mb_strtolower(trim($data['email']));
        $user = User::where('email', $email)->first();
        if ($user?->canLoginLocally()) {
            Password::broker()->sendResetLink(['email' => $email]);
        }

        // Resposta igual para conta inexistente, inativa e válida.
        return back()->with('status', 'Se houver uma conta habilitada para esse e-mail, enviaremos as instruções de recuperação.');
    }
}
