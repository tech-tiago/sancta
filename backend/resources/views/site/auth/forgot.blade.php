@extends('layouts.site', ['title' => 'Recuperar senha', 'hideFooter' => true, 'inlineStatus' => true])

@section('content')
<x-auth-shell message="Acontece. Vamos ajudar você a voltar.">
    <h1>Recuperar senha</h1>
    <p class="lead">Enviaremos um link para você criar uma nova senha.</p>
    @if (session('status'))<div class="alert alert-success" role="status"><x-sancta-icon name="mail" /><div>{{ session('status') }}</div></div>@endif
    <form class="form" method="post" action="{{ route('password.email') }}">
        @csrf
        <label for="email">E-mail
            <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required maxlength="191" @error('email') aria-invalid="true" @enderror>
            @error('email')<span class="field-error">{{ $message }}</span>@enderror
        </label>
        <button class="btn btn-primary btn-block btn-lg" type="submit">Enviar link</button>
    </form>
    <p class="auth-foot center"><a href="{{ route('login') }}">Voltar para o login</a></p>
</x-auth-shell>
@endsection
