import axios from 'axios';

import { Platform } from 'react-native';

const API_BASE_URL = Platform.OS === 'web'
  ? 'http://localhost:8000/api'
  : 'http://192.168.1.2:8000/api';

// Hardcoded token from seeder — replace with actual token after running `php artisan migrate --seed`
const AUTH_TOKEN = '1|euNhRF47SmRK1KJXkZcteL0AGphOnmkhGLYcr7URb5f7ae3b';

const client = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${AUTH_TOKEN}`,
  },
});

export async function fetchFeed(page = 1, perPage = 20) {
  const response = await client.get('/feed', {
    params: { page, per_page: perPage },
  });
  return response.data;
}

export async function searchPosts(query) {
  const response = await client.get('/search', {
    params: { q: query },
  });
  return response.data;
}

export async function logInteraction(postId, type) {
  const response = await client.post('/interactions', {
    post_id: postId,
    type,
  });
  return response.data;
}

export default client;
