@component('mail::message')
# {{ trans('mail.hello') }},

{{ $intro_text }}

@component('mail::table')
|        |          |
| ------------- | ------------- |
@if (isset($item_name))
| **{{ trans('general.item_name') }}** | {{ $item_name }} |
@endif
| **{{ trans('mail.user') }} | {{ $assigned_to }} |
@if (isset($user->location))
| **{{ trans('general.location') }}** | {{ $user->location->name }} |
@endif
@if (isset($accepted_date))
| **{{ ucfirst(trans('general.accepted')) }}** | {{ $accepted_date }} |
@endif
@if (isset($declined_date))
| **{{ ucfirst(trans('general.declined')) }}** | {{ $declined_date }} |
@endif
@if (isset($note))
| **{{ trans('general.notes') }}** | {{ $note }} |
@endif
@if (isset($item_status))
| **{{ trans('general.status') }}** | {{ $item_status }} |
@endif
@if ((isset($item_tag)) && ($item_tag!=''))
| **{{ trans('mail.asset_tag') }}** | {{ $item_tag }} |
@endif
@if (isset($item->model->category))
| **{{ trans('general.category') }}** | {{ $item->model->category->name }} |
@endif
@if ((isset($item_model)) && ($item_model!=''))
| **{{ trans('general.model_name') }}** | {{ $item_model }} |
@endif
@if (isset($item->model))
| **{{ trans('general.asset_model') }}** | {{ $item->model->name }} |
@endif
@if (isset($item_serial))
| **{{ trans('mail.serial') }}** | {{ $item_serial }} |
@endif
@if (isset($qty))
| **{{ trans('general.qty') }}** | {{ $qty }} |
@endif
@if(isset($signed_in_place_admin) && is_array($signed_in_place_admin))
| **{{ trans('general.signed_in_place_admin', ['admin' => $signed_in_place_admin['name']]) }}** | {{ $signed_in_place_admin['name'] }}@if(!empty($signed_in_place_admin['username'])) ({{ $signed_in_place_admin['username'] }})@endif@if(!empty($signed_in_place_admin['email'])) &lt;{{ $signed_in_place_admin['email'] }}&gt;@endif |
@endif
@endcomponent

{{ trans('mail.best_regards') }}

{{ $snipeSettings->site_name }}

@endcomponent
