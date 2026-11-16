<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Follow;
use App\Models\Message;
use App\Models\MovieNight;
use App\Models\Participant;
use App\Models\PrivacySetting;
use App\Models\Vote;

/** What Social does when Identity blocks, unblocks or deletes a user (docs/events.md). Runs in the consumer's transaction. */
class UserDataService
{
    /** user.blocked / user.unblocked: hides or shows the user's activity everywhere. */
    public function setActivityHidden(string $userId, bool $hidden): void
    {
        Activity::where('user_id', $userId)->update(['author_blocked' => $hidden]);
    }

    /**
     * user.deleted: everything Social keeps about the user goes. The movie nights they hosted go too (with their
     * participants, candidates and votes, by cascade). Movies they proposed on other people's nights stay, because
     * others may have voted for them.
     */
    public function deleteAll(string $userId): void
    {
        MovieNight::where('host_id', $userId)->delete();
        Vote::where('user_id', $userId)->delete();
        Participant::where('user_id', $userId)->delete();
        Message::where('user_id', $userId)->delete();
        Follow::where('follower_id', $userId)->orWhere('following_id', $userId)->delete();
        PrivacySetting::where('user_id', $userId)->delete();
        Activity::where('user_id', $userId)->delete();
    }
}
