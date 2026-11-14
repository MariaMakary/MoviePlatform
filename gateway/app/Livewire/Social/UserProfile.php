<?php

namespace App\Livewire\Social;

use App\Livewire\Concerns\WithServiceFallback;
use App\Services\AuthSession;
use App\Services\Clients\CatalogClient;
use App\Services\Clients\EngagementClient;
use App\Services\Clients\IdentityClient;
use App\Services\Clients\SocialClient;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * /users/{id}: Identity profile, Social follow counts and button, Engagement reviews and public lists.
 * Reviews, ratings and lists follow the user's privacy settings for everyone but the user; when Social cannot say
 * what is allowed, those sections stay hidden. Each section falls back on its own.
 */
class UserProfile extends Component
{
    use WithServiceFallback;

    private const REVIEWS_SHOWN = 5;

    private const LISTS_SHOWN = 6;

    private const SHOW_ALL = ['show_reviews' => true, 'show_ratings' => true, 'show_lists' => true];

    #[Locked]
    public string $id;

    public function render(AuthSession $auth, IdentityClient $identity, SocialClient $social, EngagementClient $engagement, CatalogClient $catalog): View
    {
        $user = $this->fromService('identity', fn () => $identity->users([$this->id])[0] ?? null);
        abort_if($user === null && ! $this->isUnavailable('identity'), 404);

        $isMe = ($auth->user()['id'] ?? null) === $this->id;
        $privacy = $isMe ? self::SHOW_ALL : $this->fromService('privacy', fn () => $social->userPrivacy($this->id));

        $reviews = ($privacy['show_reviews'] ?? false)
            ? $this->fromService('reviews', fn () => $engagement->userReviews($this->id, 1, self::REVIEWS_SHOWN))
            : null;

        $lists = ($privacy['show_lists'] ?? false)
            ? $this->fromService('lists', fn () => $engagement->userLists($this->id, 1, self::LISTS_SHOWN))
            : null;

        $movieIds = array_column($reviews['data'] ?? [], 'movie_id');
        $movies = $movieIds === [] ? [] : $this->fromService('movies', fn () => $catalog->movies($movieIds), []);

        return view('livewire.social.user-profile', [
            'user' => $user,
            'isMe' => $isMe,
            'privacy' => $privacy,
            'reviews' => $reviews,
            'lists' => $lists,
            'movies' => array_column($movies, null, 'id'),
        ])->title($user['name'] ?? 'Profile');
    }
}
