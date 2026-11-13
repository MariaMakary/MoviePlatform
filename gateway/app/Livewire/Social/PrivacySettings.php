<?php

namespace App\Livewire\Social;

use App\Livewire\Concerns\WithServiceFallback;
use App\Services\Clients\SocialClient;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/** /settings/privacy: what the user shows on their public profile and in followers' feeds, saved in Social. */
#[Title('Privacy settings')]
class PrivacySettings extends Component
{
    use WithServiceFallback;

    public const SETTINGS = [
        'show_reviews' => ['My reviews', 'Others see the reviews you write on your profile.', 'Your reviews are hidden from your profile and feeds. They still show on the movie pages.'],
        'show_ratings' => ['My ratings', 'Others see the stars you gave next to your reviews.', 'Your stars are hidden on your profile.'],
        'show_lists' => ['My lists', 'Others see your public lists on your profile.', 'Your lists are hidden from your profile. Anyone with a public list\'s link can still open it.'],
        'show_history' => ['My watch history', 'Followers see what you watched in their feed.', 'What you watch stays private.'],
    ];

    public array $settings = [];

    #[Locked]
    public bool $filled = false;

    public function render(SocialClient $social): View
    {
        if (! $this->filled) {
            $current = $this->fromService('privacy', fn () => $social->privacy());

            if ($current !== null) {
                $this->settings = collect(self::SETTINGS)->map(fn ($_, string $key) => (bool) $current[$key])->all();
                $this->filled = true;
            }
        }

        return view('livewire.social.privacy-settings', ['labels' => self::SETTINGS]);
    }

    public function save(SocialClient $social)
    {
        unset($this->unavailable['save']);

        $data = $this->validate(collect(self::SETTINGS)->mapWithKeys(fn ($_, string $key) => ["settings.$key" => ['required', 'boolean']])->all())['settings'];

        if ($this->fromService('save', fn () => $social->updatePrivacy($data)) === null) {
            return null;
        }

        session()->flash('success', 'Privacy settings saved.');

        return $this->redirectRoute('settings.privacy');
    }
}
