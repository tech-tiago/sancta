<tr>
<td style="padding: 0 16px;">
<table class="footer" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="content-cell" align="center">
<img src="{{ asset('images/email/portal-azul.png') }}" width="18" height="19" alt="" style="display: block; margin: 0 auto 12px; border: 0;">
{{ Illuminate\Mail\Markdown::parse($slot) }}
</td>
</tr>
</table>
</td>
</tr>
