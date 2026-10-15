@extends('layouts.site', ['title' => 'Entrar', 'hideFooter' => true, 'inlineStatus' => true])

@section('content')
<x-auth-shell message="Bem-vindo de volta. Que bom rezar com você.">
    <h1>Entrar</h1>
    <p class="lead">Use a mesma conta do aplicativo.</p>

    @if (session('status'))<div class="alert alert-success" role="status"><x-sancta-icon name="check-circle" /><div>{{ session('status') }}</div></div>@endif

    <form class="form" method="post" action="{{ route('login') }}">
        @csrf
        <label for="email">E-mail
            <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required maxlength="191" autofocus @error('email') aria-invalid="true" aria-describedby="email-erro" @enderror>
            @error('email')<span class="field-error" id="email-erro">{{ $message }}</span>@enderror
        </label>
        <label for="password">Senha
            <input id="password" type="password" name="password" autocomplete="current-password" required maxlength="200">
        </label>
        <div class="row between">
            <label class="check"><input type="checkbox" name="remember" value="1"> <span>Manter conectado</span></label>
            <a href="{{ route('password.request') }}">Esqueci a senha</a>
        </div>
        <button class="btn btn-primary btn-block btn-lg" type="submit">Entrar</button>
    </form>

    <div class="divider mt-3">ou</div>
    <p class="hint center">Entrou no app com o Google? Use “Esqueci a senha” com o mesmo e-mail para criar uma senha de acesso ao site.</p>
    <p class="auth-foot center">Novo por aqui? <a href="{{ route('register') }}">Crie sua conta</a></p>
</x-auth-shell>
@endsection
