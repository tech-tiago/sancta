@php($isCorrection = $church !== null)
@extends('layouts.site', ['title' => $isCorrection ? 'Corrigir: '.$church->name : 'Cadastrar igreja'])

@section('content')
<section class="page-head">
    <div class="container container-narrow">
        <a href="{{ $isCorrection ? route('churches.show', $church->slug) : route('churches.index') }}" class="back-link"><x-sancta-icon name="arrow-left" /> Voltar</a>
        <h1>{{ $isCorrection ? 'Corrigir informações' : 'Cadastrar uma igreja' }}</h1>
        <p class="lead">
            @if ($isCorrection)
                Encontrou algo errado em <strong>{{ $church->name }}</strong>? Conte para nós — a equipe revisa antes de publicar.
            @else
                Ajude outras pessoas a encontrar a sua paróquia ou comunidade. Depois da revisão da equipe, ela aparece no mapa.
            @endif
        </p>
    </div>
</section>

<section class="section-tight section-last">
    <div class="container container-narrow">
        @if ($errors->any())
            <div class="alert alert-error" role="alert"><x-sancta-icon name="alert" /><div>Verifique os campos destacados.</div></div>
        @endif

        <form class="card card-elevated form" method="post" enctype="multipart/form-data" action="{{ $isCorrection ? route('churches.correct.store', $church->slug) : route('churches.store') }}" novalidate>
            @csrf
            <input type="hidden" name="_started" value="{{ $startedAt }}">
            <div class="hp" aria-hidden="true"><label>Não preencha<input type="text" name="website_confirm" tabindex="-1" autocomplete="off"></label></div>

            @if ($isCorrection)
                <label>O que precisa ser corrigido? *
                    <textarea name="message" required maxlength="2000" placeholder="Ex.: a missa de domingo agora é às 9h; o telefone mudou para…" @error('message') aria-invalid="true" @enderror>{{ old('message') }}</textarea>
                    @error('message')<span class="field-error">{{ $message }}</span>@enderror
                </label>
                <p class="hint mb-0">Se quiser, informe abaixo apenas os campos que mudaram.</p>
            @endif

            <fieldset class="form">
                <legend>A igreja</legend>
                <div class="form-row">
                    <label>Nome da igreja {{ $isCorrection ? '' : '*' }}
                        <input type="text" name="name" value="{{ old('name') }}" maxlength="191" placeholder="{{ $church?->name ?? 'Paróquia Nossa Senhora…' }}" @error('name') aria-invalid="true" @enderror>
                        @error('name')<span class="field-error">{{ $message }}</span>@enderror
                    </label>
                    <label>Tipo
                        <select name="type">
                            <option value="">Selecione</option>
                            @foreach ($types as $type)
                                <option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ $type->getLabel() }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <label>Endereço {{ $isCorrection ? '' : '*' }}
                    <input type="text" name="address" value="{{ old('address') }}" maxlength="255" placeholder="{{ $church?->address ?? 'Rua, número' }}" autocomplete="street-address" @error('address') aria-invalid="true" @enderror>
                    @error('address')<span class="field-error">{{ $message }}</span>@enderror
                </label>
                <div class="form-row">
                    <label>Bairro<input type="text" name="neighborhood" value="{{ old('neighborhood') }}" maxlength="120"></label>
                    <label>CEP<input type="text" name="postal_code" value="{{ old('postal_code') }}" maxlength="12" inputmode="numeric" autocomplete="postal-code"></label>
                </div>
                <div class="form-row">
                    <label>Cidade {{ $isCorrection ? '' : '*' }}
                        <input type="text" name="city" value="{{ old('city') }}" maxlength="120" placeholder="{{ $church?->city }}" @error('city') aria-invalid="true" @enderror>
                        @error('city')<span class="field-error">{{ $message }}</span>@enderror
                    </label>
                    <label>UF {{ $isCorrection ? '' : '*' }}
                        <input type="text" name="state" value="{{ old('state') }}" maxlength="2" placeholder="{{ $church?->state ?? 'BA' }}" class="uppercase" @error('state') aria-invalid="true" @enderror>
                        @error('state')<span class="field-error">{{ $message }}</span>@enderror
                    </label>
                </div>
            </fieldset>

            <hr class="mt-0 mb-0">

            <fieldset class="form">
                <legend>Horários e contato</legend>
                <label>Horários de missa
                    <textarea name="mass_times" maxlength="1000" placeholder="Ex.: Domingo 8h e 19h; Quarta 19h30">{{ old('mass_times') }}</textarea>
                </label>
                <div class="form-row">
                    <label>Telefone<input type="tel" name="phone" value="{{ old('phone') }}" maxlength="30"></label>
                    <label>E-mail da paróquia<input type="email" name="email" value="{{ old('email') }}" maxlength="191" @error('email') aria-invalid="true" @enderror>@error('email')<span class="field-error">{{ $message }}</span>@enderror</label>
                </div>
                <div class="form-row">
                    <label>Instagram<input type="text" name="instagram" value="{{ old('instagram') }}" maxlength="80" placeholder="@paroquia"></label>
                    <label>Site<input type="url" name="website" value="{{ old('website') }}" maxlength="255" placeholder="https://" @error('website') aria-invalid="true" @enderror>@error('website')<span class="field-error">{{ $message }}</span>@enderror</label>
                </div>
                <label><span>Foto da fachada <span class="hint">(opcional, JPG, PNG ou WebP até 4 MB)</span></span>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
                    @error('image')<span class="field-error">{{ $message }}</span>@enderror
                </label>
                @unless ($isCorrection)
                    <label>Observações<textarea name="message" maxlength="2000">{{ old('message') }}</textarea></label>
                @endunless
            </fieldset>

            @guest
                <hr class="mt-0 mb-0">
                <fieldset class="form">
                    <legend>Sobre você</legend>
                    <div class="form-row">
                        <label>Seu nome *<input type="text" name="submitter_name" value="{{ old('submitter_name') }}" maxlength="120" required autocomplete="name" @error('submitter_name') aria-invalid="true" @enderror>@error('submitter_name')<span class="field-error">{{ $message }}</span>@enderror</label>
                        <label>Seu e-mail *<input type="email" name="submitter_email" value="{{ old('submitter_email') }}" maxlength="191" required autocomplete="email" @error('submitter_email') aria-invalid="true" @enderror>@error('submitter_email')<span class="field-error">{{ $message }}</span>@enderror</label>
                    </div>
                </fieldset>
            @endguest

            <label class="check"><input type="checkbox" name="consent" value="1" required @checked(old('consent'))> <span>Confirmo que as informações são verdadeiras e autorizo o uso delas no mapa do Sancta, conforme a <a href="{{ route('pages.show', 'politica-de-privacidade') }}" target="_blank">Política de Privacidade</a>.</span></label>
            @error('consent')<span class="field-error">{{ $message }}</span>@enderror

            <div><button class="btn btn-primary btn-lg" type="submit">{{ $isCorrection ? 'Enviar correção' : 'Enviar para revisão' }}</button></div>
        </form>
    </div>
</section>
@endsection
