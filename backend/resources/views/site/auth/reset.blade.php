@extends('layouts.site', ['title' => 'Nova senha', 'hideFooter' => true, 'inlineStatus' => true])

@section('content')
<x-auth-shell message="Uma nova senha, e seguimos juntos.">
    <h1>Criar nova senha</h1>
    <p class="lead">Escolha uma senha que você não use em outros lugares.</p>
    <form class="form" method="post" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label for="email">E-mail
            <input id="email" type="email" name="email" value="{{ old('email', $email) }}" autocomplete="email" required maxlength="191" @error('email') aria-invalid="true" @enderror>
            @error('email')<span class="field-error">{{ $message }}</span>@enderror
        </label>
        <label for="password">Nova senha
            <input id="password" type="password" name="password" autocomplete="new-password" required minlength="8" maxlength="200" aria-describedby="senha-dica" @error('password') aria-invalid="true" @enderror>
            <span class="hint" id="senha-dica">Mínimo de 8 caracteres, com letras e números.</span>
            @error('password')<span class="field-error">{{ $message }}</span>@enderror
        </label>
        <label for="password_confirmation">Confirme a nova senha
            <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required maxlength="200">
        </label>
        <button class="btn btn-primary btn-block btn-lg" type="submit">Salvar nova senha</button>
    </form>
</x-auth-shell>
@endsection
