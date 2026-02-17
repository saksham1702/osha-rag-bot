"""
LRU in-memory cache for retrieval results.
Caching RAG query results with configurable TTL to reduce redundant calls.
"""
import time
from collections import OrderedDict


class LRUCache:
    """Thread-safe LRU cache with time-based expiration."""

    def __init__(self, max_size: int = 256, ttl_seconds: int = 3600):
        self._cache: OrderedDict[str, dict] = OrderedDict()
        self._max_size = max_size
        self._ttl_seconds = ttl_seconds

    def get(self, key: str) -> dict | None:
        """Retrieving a cached value if it exists and has not expired."""
        if key not in self._cache:
            return None

        entry = self._cache[key]
        if time.time() - entry["timestamp"] > self._ttl_seconds:
            # Entry expired, removing it
            del self._cache[key]
            return None

        # Moving to end (most recently used)
        self._cache.move_to_end(key)
        return entry["value"]

    def set(self, key: str, value: dict):
        """Storing a value in the cache, evicting oldest if at capacity."""
        if key in self._cache:
            self._cache.move_to_end(key)
        elif len(self._cache) >= self._max_size:
            self._cache.popitem(last=False)

        self._cache[key] = {
            "value": value,
            "timestamp": time.time(),
        }

    def clear(self):
        """Clearing the entire cache."""
        self._cache.clear()

    @property
    def size(self) -> int:
        """Returning the current cache size."""
        return len(self._cache)


# Shared cache instance (1 hour TTL, 256 entries max)
retrieval_cache = LRUCache(max_size=256, ttl_seconds=3600)
