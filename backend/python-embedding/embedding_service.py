"""Embedding service module for generating text embeddings.

Loads sentence-transformers all-MiniLM-L6-v2 model when available,
falls back to deterministic mock embeddings based on text hash.
"""

import hashlib
import struct
from typing import Optional

import numpy as np

MODEL_NAME: str = "all-MiniLM-L6-v2"
EMBEDDING_DIMENSIONS: int = 384

_model: Optional[object] = None
_use_mock: bool = False


def _load_model() -> None:
    """Attempt to load the sentence-transformers model."""
    global _model, _use_mock

    try:
        from sentence_transformers import SentenceTransformer

        _model = SentenceTransformer(MODEL_NAME)
        _use_mock = False
    except (ImportError, OSError, Exception):
        _model = None
        _use_mock = True


def _generate_mock_embedding(text: str) -> list[float]:
    """Generate a deterministic mock embedding based on text hash.

    Produces a consistent 384-dimensional vector for the same input text
    using SHA-256 hash expanded to fill all dimensions.
    """
    hash_bytes = hashlib.sha256(text.encode("utf-8")).digest()

    # Expand hash to fill 384 dimensions deterministically
    values: list[float] = []
    for i in range(EMBEDDING_DIMENSIONS):
        # Use different hash iterations to generate enough values
        chunk_hash = hashlib.sha256(hash_bytes + struct.pack(">I", i)).digest()
        # Convert first 4 bytes to a float between -1 and 1
        int_val = struct.unpack(">I", chunk_hash[:4])[0]
        float_val = (int_val / (2**32 - 1)) * 2.0 - 1.0
        values.append(float_val)

    # Normalize to unit vector
    arr = np.array(values, dtype=np.float32)
    norm = np.linalg.norm(arr)
    if norm > 0:
        arr = arr / norm

    return arr.tolist()


def generate_embedding(text: str) -> list[float]:
    """Generate an embedding vector for the given text.

    Args:
        text: The input text to embed.

    Returns:
        A list of floats representing the 384-dimensional embedding vector.
    """
    global _model, _use_mock

    if _model is None and not _use_mock:
        _load_model()

    if _use_mock:
        return _generate_mock_embedding(text)

    embedding = _model.encode(text, normalize_embeddings=True)
    return embedding.tolist()


# Load model on module import
_load_model()
