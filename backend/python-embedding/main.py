"""FastAPI application for text embedding generation.

Provides endpoints for generating text embeddings using
sentence-transformers all-MiniLM-L6-v2 model.
"""

from fastapi import FastAPI
from pydantic import BaseModel, Field

from embedding_service import (
    EMBEDDING_DIMENSIONS,
    MODEL_NAME,
    generate_embedding,
)

app: FastAPI = FastAPI(
    title="Guised Up Embedding Service",
    description="Text embedding generation service for the Real Connections Feed",
    version="1.0.0",
)


class EmbedRequest(BaseModel):
    """Request model for the /embed endpoint."""

    text: str = Field(..., min_length=1, description="The text to generate an embedding for")


class EmbedResponse(BaseModel):
    """Response model for the /embed endpoint."""

    embedding: list[float] = Field(..., description="The embedding vector")
    dimensions: int = Field(default=EMBEDDING_DIMENSIONS, description="Number of dimensions")
    model: str = Field(default=MODEL_NAME, description="Model used for embedding generation")


class HealthResponse(BaseModel):
    """Response model for the /health endpoint."""

    status: str = Field(default="healthy", description="Service health status")
    service: str = Field(default="embedding-service", description="Service name")


@app.get("/health", response_model=HealthResponse)
def health_check() -> HealthResponse:
    """Health check endpoint."""
    return HealthResponse()


@app.post("/embed", response_model=EmbedResponse)
def embed_text(request: EmbedRequest) -> EmbedResponse:
    """Generate an embedding for the provided text.

    Args:
        request: The embed request containing the text to embed.

    Returns:
        The embedding vector with metadata.
    """
    embedding = generate_embedding(request.text)

    return EmbedResponse(
        embedding=embedding,
        dimensions=EMBEDDING_DIMENSIONS,
        model=MODEL_NAME,
    )
