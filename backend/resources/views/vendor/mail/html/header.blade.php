@props(['url'])
{{-- Cabeçalho Sancta: faixa Azul Manto com o wordmark Pergaminho (PNG: clientes de e-mail não exibem SVG). --}}
<tr>
<td class="header-cell" align="center">
<table class="header-card" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="header" align="center" bgcolor="#1F3D9C">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ asset('images/email/sancta-wordmark-pergaminho.png') }}" class="logo" width="128" height="30" alt="{{ trim($slot) ?: config('app.name') }}">
</a>
</td>
</tr>
</table>
</td>
</tr>
