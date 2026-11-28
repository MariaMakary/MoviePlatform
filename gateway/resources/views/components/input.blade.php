@props(['label', 'name', 'type' => 'text'])

<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-fg">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
        {{ $attributes->class([
            'mt-1 block w-full rounded-lg border bg-surface-1 px-3 py-2 text-sm text-fg placeholder-subtle transition focus:ring-[1.5px] focus:ring-neon-rose/75 focus:outline-none',
            'border-line-strong' => ! $errors->has($name),
            'border-neon-rose' => $errors->has($name),
        ]) }}
        @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror>
    @error($name)
        <p id="{{ $name }}-error" class="mt-1 text-sm text-neon-rose">{{ $message }}</p>
    @enderror
</div>
