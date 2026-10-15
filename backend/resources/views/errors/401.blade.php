@extends('errors.minimal-sancta')
@section('code', '401')
@section('sky', 'aurora')
@section('title', 'Entre para continuar')
@section('message', 'Esta página é só para quem entrou na conta. Use a mesma conta do aplicativo.')
@section('action')
    <a class="btn btn-primary" href="/entrar">Entrar</a>
@endsection
