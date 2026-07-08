import React, { useState, useEffect, useCallback, useRef } from 'react';
import { View, FlatList, RefreshControl, Platform, StyleSheet } from 'react-native';
import { COLORS, SPACING } from '../styles/theme';
import { fetchFeed, searchPosts, logInteraction } from '../services/api';
import PostCard from '../components/PostCard';
import SearchBar from '../components/SearchBar';
import LoadingState from '../components/LoadingState';
import EmptyState from '../components/EmptyState';
import ErrorState from '../components/ErrorState';

export default function FeedScreen() {
  const [posts, setPosts] = useState([]);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [loadingMore, setLoadingMore] = useState(false);
  const [error, setError] = useState(null);
  const [isSearchActive, setIsSearchActive] = useState(false);

  const viewedPostIds = useRef(new Set());

  const loadFeed = useCallback(async (pageNum = 1, append = false) => {
    try {
      setError(null);
      const result = await fetchFeed(pageNum);
      const newPosts = result.data;
      const meta = result.meta;

      if (append) {
        setPosts((prev) => [...prev, ...newPosts]);
      } else {
        setPosts(newPosts);
      }
      setTotal(meta.total);
      setPage(pageNum);
    } catch (err) {
      setError(err.message || 'Failed to load feed');
    }
  }, []);

  useEffect(() => {
    (async () => {
      setLoading(true);
      await loadFeed(1);
      setLoading(false);
    })();
  }, [loadFeed]);

  const handleRefresh = useCallback(async () => {
    setRefreshing(true);
    setIsSearchActive(false);
    await loadFeed(1);
    setRefreshing(false);
  }, [loadFeed]);

  const handleLoadMore = useCallback(async () => {
    if (loadingMore || isSearchActive) return;
    if (posts.length >= total) return;

    setLoadingMore(true);
    await loadFeed(page + 1, true);
    setLoadingMore(false);
  }, [loadingMore, isSearchActive, posts.length, total, page, loadFeed]);

  const handleSearch = useCallback(async (query) => {
    setIsSearchActive(true);
    setLoading(true);
    setError(null);
    try {
      const result = await searchPosts(query);
      setPosts(result.data);
      setTotal(result.data.length);
    } catch (err) {
      setError(err.message || 'Search failed');
    }
    setLoading(false);
  }, []);

  const handleClearSearch = useCallback(() => {
    setIsSearchActive(false);
    setLoading(true);
    loadFeed(1).then(() => setLoading(false));
  }, [loadFeed]);

  const onViewableItemsChanged = useCallback(({ viewableItems }) => {
    if (!viewableItems) return;
    viewableItems.forEach((item) => {
      const postId = item.item.id;
      if (!viewedPostIds.current.has(postId)) {
        viewedPostIds.current.add(postId);
        logInteraction(postId, 'view').catch(() => {});
      }
    });
  }, []);

  const viewabilityConfig = useRef({
    itemVisiblePercentThreshold: 50,
    minimumViewTime: 1000,
  });

  if (loading && posts.length === 0) {
    return (
      <View style={styles.screen}>
        <SearchBar onSearch={handleSearch} onClear={handleClearSearch} />
        <LoadingState />
      </View>
    );
  }

  if (error && posts.length === 0) {
    return (
      <View style={styles.screen}>
        <SearchBar onSearch={handleSearch} onClear={handleClearSearch} />
        <ErrorState message={error} onRetry={handleRefresh} />
      </View>
    );
  }

  const flatListProps = Platform.OS !== 'web' ? {
    onViewableItemsChanged,
    viewabilityConfig: viewabilityConfig.current,
  } : {};

  return (
    <View style={styles.screen}>
      <SearchBar onSearch={handleSearch} onClear={handleClearSearch} />
      <FlatList
        data={posts}
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => <PostCard post={item} />}
        contentContainerStyle={styles.listContent}
        ListEmptyComponent={
          <EmptyState message={isSearchActive ? 'No results found' : undefined} />
        }
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={handleRefresh}
            tintColor={COLORS.primary}
            colors={[COLORS.primary]}
          />
        }
        onEndReached={handleLoadMore}
        onEndReachedThreshold={0.5}
        {...flatListProps}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  screen: {
    flex: 1,
    backgroundColor: COLORS.background,
  },
  listContent: {
    paddingTop: SPACING.sm,
    paddingBottom: SPACING.xl,
  },
});
