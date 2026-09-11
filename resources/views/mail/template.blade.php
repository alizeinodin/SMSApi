<x-mail::message>
# کد تایید

@if(! empty($code))
کد تایید شما:

<x-mail::panel>
**{{ $code }}**
</x-mail::panel>
@endif

@foreach($parameters as $name => $value)
@continue(strtoupper((string) $name) === 'CODE' && ! empty($code))
- **{{ $name }}:** {{ $value }}
@endforeach

با احترام،<br>
{{ config('app.name', 'SMSApi') }}
</x-mail::message>
