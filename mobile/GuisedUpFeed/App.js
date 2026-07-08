import React from 'react';
import { StatusBar, Text, View, Platform, StyleSheet } from 'react-native';
import { COLORS, SPACING, TYPOGRAPHY } from './src/styles/theme';
import FeedScreen from './src/screens/FeedScreen';

const MAX_WIDTH = 480;

export default function App() {
  return (
    <View style={styles.outer}>
      <View style={styles.container}>
        <StatusBar barStyle="dark-content" backgroundColor={COLORS.background} />
        <View style={styles.header}>
          <Text style={styles.title}>Guised Up</Text>
          <Text style={styles.subtitle}>Real Connections</Text>
        </View>
        <FeedScreen />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  outer: {
    flex: 1,
    height: '100%',
    backgroundColor: COLORS.background,
    alignItems: 'center',
  },
  container: {
    flex: 1,
    width: '100%',
    maxWidth: Platform.OS === 'web' ? MAX_WIDTH : undefined,
    backgroundColor: COLORS.background,
  },
  header: {
    paddingHorizontal: SPACING.base,
    paddingTop: SPACING.base,
    paddingBottom: SPACING.md,
  },
  title: {
    ...TYPOGRAPHY.heading,
    color: COLORS.primary,
  },
  subtitle: {
    ...TYPOGRAPHY.caption,
    marginTop: 2,
  },
});
