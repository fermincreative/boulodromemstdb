<div class="field {{ $class }} @error($name) invalid @enderror">
    <label for="{{ $name }}">
        {{ $label }}
        @unless ($required ?? false) <span class="optional">{{ __('signup.optional') }}</span> @endunless
    </label>
    <input
        type="{{ $type ?? 'text' }}"
        id="{{ $name }}"
        name="{{ $name }}"
        value="{{ old($name, $value ?? null) }}"
        autocomplete="{{ $autocomplete ?? 'off' }}"
        @if ($placeholder ?? null) placeholder="{{ $placeholder }}" @endif
        @if ($inputmode ?? null) inputmode="{{ $inputmode }}" @endif
        @if ($required ?? false) required @endif
        @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
    >
    @error($name)
        <span class="error" id="{{ $name }}-error">{{ $message }}</span>
    @else
        @if ($hint ?? null) <span class="hint">{{ $hint }}</span> @endif
    @enderror
</div>
