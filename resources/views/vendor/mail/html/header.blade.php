@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (trim($slot) === 'Laravel' || trim($slot) === config('app.name'))
<img src="{{ config('app.url') }}/images/logo.png" class="logo" alt="Morex" style="height: 40px; width: auto; max-width: 200px;">
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
