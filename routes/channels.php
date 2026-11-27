<?php

use App\Models\ChatConversation;
use App\Models\User;
use App\Services\Chat\ChatPresenter;
use App\Services\Chat\ChatVisibility;
use Illuminate\Support\Facades\Broadcast;

/*
| Who may listen to what. Live chat's channels are described in
| App\Services\Chat\ChatBroadcaster; the rules below are ChatVisibility's,
| so a channel never shows what the inbox would not.
|
| Conversations are looked up across clients on purpose: the request-level
| client scope is not switched on for this endpoint, and ChatVisibility
| checks client access itself.
*/

Broadcast::channel('App.Models.User.{id}', function (User $user, $id) {
    return (string) $user->id === (string) $id;
});

// One person's inbox: list order, unread counts.
Broadcast::channel('chat.user.{id}', function (User $user, $id) {
    return (string) $user->id === (string) $id;
});

// A conversation's room: the client who owns it and the team members who may
// see it. Presence, so each side knows who is there; client-safe payloads only.
Broadcast::channel('chat.{conversation}', function (User $user, $conversation) {
    $chat = ChatConversation::acrossClients()->find($conversation);

    // Someone on the team looking at the portal as this client stays a team
    // member here: they never join as the client.
    if (! $chat || ! (ChatVisibility::isOwner($user, $chat) || ChatVisibility::canSee($user, $chat))) {
        return false;
    }

    return ChatPresenter::person($user, withId: true) + [
        'side' => ChatVisibility::isOwner($user, $chat) ? 'client' : 'team',
    ];
});

// The team's side of a conversation: notes and everything else the client must not see.
Broadcast::channel('chat.{conversation}.team', function (User $user, $conversation) {
    $chat = ChatConversation::acrossClients()->find($conversation);

    return $chat !== null && ChatVisibility::canSee($user, $chat);
});
