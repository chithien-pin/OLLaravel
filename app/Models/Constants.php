<?php

namespace App\Models;

final class Constants
{
    const contentVideo = 1;

    const unblocked = 0;
    const blocked = 1;

    // Database notification types
    const notificationTypeFollow = 1;
    const notificationTypeComment = 2;
    const notificationTypeLike = 3;
    const notificationTypeLikeProfile = 4;
    const notificationTypeRedeemCompleted = 5;
    const notificationTypeRedeemCancelled = 6;
    const notificationTypeMessage = 7;
    const notificationTypeLiveStream = 8;
    const notificationTypeGift = 9;
    const notificationTypeAdmin = 10;
    const notificationTypeHandshakeAccepted = 11;

    // Push notification event types
    const eventTypeUserFollow = 'user_follow';
    const eventTypeNewMessage = 'new_message';
    const eventTypeLiveStreamStart = 'live_stream_start';
    const eventTypePostLike = 'post_like';
    const eventTypePostComment = 'post_comment';
    const eventTypeGiftReceived = 'gift_received';
    const eventTypeAdminAnnouncement = 'admin_announcement';
    const eventTypeProfileLike = 'profile_like';
    const eventTypeHandshakeAccepted = 'handshake_accepted';

    // Push notification actions
    const actionNavigateToProfile = 'navigate_to_profile';
    const actionOpenChat = 'open_chat';
    const actionJoinLiveStream = 'join_live_stream';
    const actionViewPost = 'view_post';
    const actionViewNotification = 'view_notification';
    const actionViewFollowing = 'view_following';

    // Screens for navigation
    const screenUserDetail = 'UserDetailScreen';
    const screenChat = 'ChatScreen';
    const screenPersonStreaming = 'PersonStreamingScreen';
    const screenPostDetail = 'PostDetailScreen';
    const screenNotification = 'NotificationScreen';
    const screenFollow = 'SuggestionsScreen';
}
