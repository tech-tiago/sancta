@extends('layouts.site', ['title' => 'Criar conta', 'hideFooter' => true, 'inlineStatus' => true])

@section('content')
<x-auth-shell message="Uma conta para o app e para o site. Sua jornada de fé, todos os dias.">
    <h1>Crie sua conta</h1>
    <p class="lead">Leva menos de um minuto.</p>

    <form class="form" method="post" action="{{ route('register') }}">
        @csrf
        <label for="name">Nome
            <input id="name" type="text" name="name" value="{{ old('name') }}" autocomplete="name" required maxlength="120" @error('name') aria-invalid="true" @enderror>
            @error('name')<span class="field-error">{{ $message }}</span>@enderror
        </label>
        <label for="email">E-mail
            <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required maxlength="191" @error('email') aria-invalid="true" @enderror>
            @error('email')<span class="field-error">{{ $message }}</span>@enderror
        </label>
        <label for="password">Senha
            <input id="password" type="password" name="password" autocomplete="new-password" required minlength="8" maxlength="200" aria-describedby="senha-dica" @error('password') aria-invalid="true" @enderror>
            <span class="hint" id="senha-dica">Mínimo de 8 caracteres, com letras e números.</span>
            @error('password')<span class="field-error">{{ $message }}</span>@enderror
        </label>
        <label for="password_confirmation">Confirme a senha
            <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required maxlength="200">
        </label>
        <label class="check"><input type="checkbox" name="accept_terms" value="1" required> <span>Li e aceito os <a href="{{ route('pages.show', 'termos-de-uso') }}" target="_blank">Termos de uso</a> e a <a href="{{ route('pages.show', 'politica-de-privacidade') }}" target="_blank">Política de privacidade</a>.</span></label>
        @error('accept_terms')<span class="field-error">{{ $message }}</span>@enderror
        <label class="check"><input type="checkbox" name="marketing_opt_in" value="1"> <span>Quero receber novidades, novenas e conteúdos especiais por e-mail.</span></label>
        <button class="btn btn-primary btn-block btn-lg" type="submit">Criar conta</button>
    </form>
    <p class="auth-foot center">Já tem conta? <a href="{{ route('login') }}">Entrar</a></p>
</x-auth-shell>
@endsection
