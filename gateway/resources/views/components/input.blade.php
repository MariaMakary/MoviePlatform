@props(['label', 'name', 'type' => 'text'])

<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-gray-200">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
        {{ $attributes->class([
            'mt-1 block w-full rounded-lg border bg-white/5 px-3 py-2 text-sm text-white placeholder-gray-500 transition focus:ring-[1.5px] focus:ring-neon-rose/75 focus:outline-none',
            'border-white/10' => ! $errors->has($name),
            'border-neon-rose' => $errors->has($name),
        ]) }}
        @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror>
    @error($name)
        <p id="{{ $name }}-error" class="mt-1 text-sm text-neon-rose">{{ $message }}</p>
    @enderror
</div>
