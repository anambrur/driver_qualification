<?php

namespace App\Http\Requests\Platform;

use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PostRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug((string) ($this->input('slug') ?: $this->input('title'))),
            'remove_cover' => $this->boolean('remove_cover'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Post|null $post */
        $post = $this->route('post');

        return [
            'title' => ['required', 'string', 'max:160'],
            // "apply" would collide with the company application route /{slug}/apply
            'slug' => ['required', 'string', 'max:180', 'not_in:apply,application', Rule::unique('posts', 'slug')->ignore($post?->getKey())],
            'excerpt' => ['nullable', 'string', 'max:300'],
            'meta_description' => ['nullable', 'string', 'max:300'],
            'body' => ['nullable', 'string', 'max:200000'],
            'published_at' => ['nullable', 'date'],
            // Raster images only on the public disk
            'cover' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'remove_cover' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['published_at' => 'publish date', 'cover' => 'cover image'];
    }

    /**
     * @return array<string, mixed>
     */
    public function postAttributes(): array
    {
        return $this->safe()->except(['cover', 'remove_cover']);
    }
}
