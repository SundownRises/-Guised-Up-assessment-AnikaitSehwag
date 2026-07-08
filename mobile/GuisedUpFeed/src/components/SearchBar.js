import React, { useState, useCallback, useRef } from 'react';
import { View, TextInput, TouchableOpacity, Text, StyleSheet } from 'react-native';
import { COLORS, SPACING, CARD } from '../styles/theme';

export default function SearchBar({ onSearch, onClear }) {
  const [query, setQuery] = useState('');
  const debounceRef = useRef(null);

  const handleChange = useCallback((text) => {
    setQuery(text);

    if (debounceRef.current) {
      clearTimeout(debounceRef.current);
    }

    if (!text.trim()) {
      onClear();
      return;
    }

    debounceRef.current = setTimeout(() => {
      onSearch(text.trim());
    }, 500);
  }, [onSearch, onClear]);

  const handleClear = useCallback(() => {
    setQuery('');
    if (debounceRef.current) {
      clearTimeout(debounceRef.current);
    }
    onClear();
  }, [onClear]);

  return (
    <View style={styles.container}>
      <TextInput
        style={styles.input}
        placeholder="Search posts..."
        placeholderTextColor={COLORS.textSecondary}
        value={query}
        onChangeText={handleChange}
        returnKeyType="search"
      />
      {query.length > 0 && (
        <TouchableOpacity onPress={handleClear} style={styles.clearButton}>
          <Text style={styles.clearText}>x</Text>
        </TouchableOpacity>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORS.card,
    borderRadius: CARD.borderRadius,
    borderWidth: 1,
    borderColor: COLORS.border,
    marginHorizontal: SPACING.base,
    marginBottom: SPACING.base,
    paddingHorizontal: SPACING.md,
  },
  input: {
    flex: 1,
    height: 44,
    fontSize: 15,
    color: COLORS.textPrimary,
  },
  clearButton: {
    padding: SPACING.sm,
  },
  clearText: {
    fontSize: 18,
    color: COLORS.textSecondary,
    fontWeight: '600',
  },
});
