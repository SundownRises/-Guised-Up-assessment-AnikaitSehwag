import React, { useState, useRef, useCallback } from 'react';
import { View, Text, Image, Pressable, Platform, StyleSheet } from 'react-native';
import { COLORS, SPACING, TYPOGRAPHY, CARD } from '../styles/theme';
import { logInteraction } from '../services/api';

export default function PostCard({ post }) {
  const [reacted, setReacted] = useState(false);
  const reactedRef = useRef(false);
  const pendingRef = useRef(false);
  const lastReactionTime = useRef(0);
  const lastTapRef = useRef(0);
  const heartTappedRef = useRef(false);

  const triggerReaction = useCallback(async () => {
    const now = Date.now();
    if (pendingRef.current || now - lastReactionTime.current < 300) return;
    pendingRef.current = true;
    lastReactionTime.current = now;

    const newState = !reactedRef.current;
    reactedRef.current = newState;
    setReacted(newState);
    try {
      await logInteraction(post.id, 'reaction');
    } catch {
      reactedRef.current = !newState;
      setReacted(!newState);
    } finally {
      pendingRef.current = false;
    }
  }, [post.id]);

  const handleHeartPress = useCallback(() => {
    heartTappedRef.current = true;
    triggerReaction();
    setTimeout(() => { heartTappedRef.current = false; }, 50);
  }, [triggerReaction]);

  const handleDoubleTap = useCallback(() => {
    if (heartTappedRef.current) return;
    const now = Date.now();
    if (now - lastTapRef.current < 400) {
      triggerReaction();
      lastTapRef.current = 0;
    } else {
      lastTapRef.current = now;
    }
  }, [triggerReaction]);

  const cardProps = Platform.OS === 'web'
    ? { onClick: handleDoubleTap }
    : { onStartShouldSetResponder: () => true, onResponderRelease: handleDoubleTap };

  return (
    <View style={styles.card} {...cardProps}>
      <View style={styles.header}>
        <View style={styles.avatar}>
          {post.user.avatar_url ? (
            <Image source={{ uri: post.user.avatar_url }} style={styles.avatarImage} />
          ) : (
            <Text style={styles.avatarText}>{post.user.name.charAt(0)}</Text>
          )}
        </View>
        <View style={styles.headerText}>
          <Text style={styles.username}>{post.user.name}</Text>
          <Text style={styles.timeAgo}>{post.time_ago}</Text>
        </View>
      </View>

      <Text style={styles.body}>{post.text}</Text>

      {post.image_url && (
        <Image source={{ uri: post.image_url }} style={styles.postImage} resizeMode="cover" />
      )}

      <View style={styles.footer}>
        <Pressable style={styles.heartButton} onPress={handleHeartPress}>
          <Text style={[styles.heartIcon, reacted && styles.heartActive]}>
            {reacted ? '❤️' : '♡'}
          </Text>
        </Pressable>
        <Text style={styles.authenticityBadge}>
          {Math.round(post.authenticity_score * 100)}% authentic
        </Text>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: COLORS.card,
    borderRadius: CARD.borderRadius,
    padding: SPACING.base,
    marginHorizontal: SPACING.base,
    marginBottom: SPACING.base,
    borderWidth: 1,
    borderColor: COLORS.border,
    cursor: 'pointer',
    userSelect: 'none',
    ...CARD.shadow,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: SPACING.md,
  },
  avatar: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: COLORS.background,
    borderWidth: 2,
    borderColor: COLORS.accent,
    alignItems: 'center',
    justifyContent: 'center',
    overflow: 'hidden',
  },
  avatarImage: {
    width: 40,
    height: 40,
    borderRadius: 20,
  },
  avatarText: {
    fontSize: 16,
    fontWeight: '600',
    color: COLORS.accent,
  },
  headerText: {
    marginLeft: SPACING.md,
  },
  username: {
    ...TYPOGRAPHY.username,
  },
  timeAgo: {
    ...TYPOGRAPHY.caption,
    marginTop: 2,
  },
  body: {
    ...TYPOGRAPHY.body,
    marginBottom: SPACING.md,
  },
  postImage: {
    width: '100%',
    aspectRatio: 4 / 3,
    borderRadius: SPACING.sm,
    marginBottom: SPACING.md,
  },
  footer: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  heartButton: {
    padding: SPACING.xs,
    cursor: 'pointer',
  },
  heartIcon: {
    fontSize: 24,
    color: COLORS.textSecondary,
  },
  heartActive: {
    color: COLORS.primary,
  },
  authenticityBadge: {
    ...TYPOGRAPHY.caption,
    fontSize: 12,
  },
});
