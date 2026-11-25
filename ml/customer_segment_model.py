#!/usr/bin/env python3
"""Segmentation clients RFM - k-means.

Implementation en bibliotheque standard uniquement. Les variables sont la
recence (jours depuis le dernier achat), la frequence (nombre de ventes) et le
montant total achete. Les clusters sont nommes selon leur valeur relative.

Usage :
  python3 ml/customer_segment_model.py train --input dataset.csv --model-out model.json --metrics-out metrics.json
  python3 ml/customer_segment_model.py predict --model model.json --input features.csv --output predictions.csv
"""

from __future__ import annotations

import argparse
import csv
import json
import math
import random
import sys
from datetime import datetime, timezone

ID_COLUMN = "sample_id"
FEATURES = ["recency_days", "frequency", "monetary"]
RANDOM_SEED = 42
DEFAULT_K = 3
MAX_ITERATIONS = 100


def read_csv(path: str) -> tuple[list[str], list[dict[str, str]]]:
    with open(path, newline="", encoding="utf-8") as handle:
        reader = csv.DictReader(handle)
        return list(reader.fieldnames or []), list(reader)


def to_float(value: object, default: float = 0.0) -> float:
    if value is None:
        return default
    text = str(value).strip()
    if text == "":
        return default
    try:
        number = float(text)
    except ValueError:
        return default
    if not math.isfinite(number):
        return default
    return number


def fit_scaler(vectors: list[list[float]]) -> tuple[list[float], list[float]]:
    if not vectors:
        return [], []

    width = len(vectors[0])
    count = len(vectors)
    means = [sum(vector[index] for vector in vectors) / count for index in range(width)]
    stds = []

    for index in range(width):
        variance = sum((vector[index] - means[index]) ** 2 for vector in vectors) / count
        std = math.sqrt(variance)
        stds.append(std if std > 1e-9 else 1.0)

    return means, stds


def scale(vector: list[float], means: list[float], stds: list[float]) -> list[float]:
    return [(value - mean) / std for value, mean, std in zip(vector, means, stds)]


def inverse_scale(vector: list[float], means: list[float], stds: list[float]) -> list[float]:
    return [value * std + mean for value, mean, std in zip(vector, means, stds)]


def squared_distance(left: list[float], right: list[float]) -> float:
    return sum((a - b) ** 2 for a, b in zip(left, right))


def nearest(point: list[float], centroids: list[list[float]]) -> int:
    return min(range(len(centroids)), key=lambda index: squared_distance(point, centroids[index]))


def kmeans_pp(points: list[list[float]], k: int, rng: random.Random) -> list[list[float]]:
    centroids = [list(points[rng.randrange(len(points))])]

    while len(centroids) < k:
        distances = [min(squared_distance(point, centroid) for centroid in centroids) for point in points]
        total = sum(distances)

        if total <= 0.0:
            for point in points:
                if all(squared_distance(point, centroid) > 1e-12 for centroid in centroids):
                    centroids.append(list(point))
                    break
            else:
                break
            continue

        target = rng.random() * total
        cumulative = 0.0
        chosen = points[-1]

        for point, distance in zip(points, distances):
            cumulative += distance
            if cumulative >= target:
                chosen = point
                break

        centroids.append(list(chosen))

    return centroids


def kmeans(points: list[list[float]], k: int, rng: random.Random):
    centroids = kmeans_pp(points, k, rng)
    assignments = [-1] * len(points)
    iteration = 0

    for iteration in range(1, MAX_ITERATIONS + 1):
        new_assignments = [nearest(point, centroids) for point in points]

        if new_assignments == assignments:
            assignments = new_assignments
            break

        assignments = new_assignments

        for cluster in range(k):
            members = [point for point, assignment in zip(points, assignments) if assignment == cluster]

            if members:
                centroids[cluster] = [
                    sum(column) / len(members) for column in zip(*members)
                ]
            else:
                farthest = max(
                    range(len(points)),
                    key=lambda index: squared_distance(points[index], centroids[assignments[index]]),
                )
                centroids[cluster] = list(points[farthest])

    inertia = sum(squared_distance(point, centroids[assignment]) for point, assignment in zip(points, assignments))
    sizes = [assignments.count(cluster) for cluster in range(k)]

    return assignments, centroids, inertia, sizes, iteration


def zscores(values: list[float]) -> list[float]:
    if not values:
        return []

    mean = sum(values) / len(values)
    variance = sum((value - mean) ** 2 for value in values) / len(values)
    std = math.sqrt(variance)

    if std <= 1e-9:
        return [0.0 for _ in values]

    return [(value - mean) / std for value in values]


def assign_labels(centroids_original: list[list[float]]) -> list[str]:
    k = len(centroids_original)
    recency = [centroid[0] for centroid in centroids_original]
    frequency = [centroid[1] for centroid in centroids_original]
    monetary = [centroid[2] for centroid in centroids_original]

    recency_z = zscores(recency)
    frequency_z = zscores(frequency)
    monetary_z = zscores(monetary)
    value = [
        frequency_z[index] + monetary_z[index] - recency_z[index]
        for index in range(k)
    ]
    order = sorted(range(k), key=lambda index: value[index], reverse=True)
    labels = ["Clients occasionnels"] * k

    inactive = [
        index for index in range(k)
        if frequency[index] <= 0.0 and monetary[index] <= 0.0
    ]

    for index in inactive:
        labels[index] = "Clients inactifs"

    remaining = [index for index in order if index not in inactive]

    if len(remaining) == 1:
        names = ["Clients"]
    elif len(remaining) == 2:
        names = ["Clients fidèles", "Clients à relancer"]
    else:
        names = ["Clients fidèles", "Clients occasionnels", "Clients à relancer"]

    for position, index in enumerate(remaining):
        labels[index] = names[position] if position < len(names) else "Clients occasionnels"

    return labels


def value_score(vector: list[float]) -> float:
    return round(0.4 * vector[1] + 0.4 * vector[2] - 0.2 * vector[0], 4)


def train(input_path: str, model_path: str, metrics_path: str) -> dict:
    _, rows = read_csv(input_path)

    if len(rows) == 0:
        raise SystemExit("Jeu de donnees vide : aucun client a segmenter.")

    vectors = [[to_float(row.get(feature)) for feature in FEATURES] for row in rows]
    means, stds = fit_scaler(vectors)
    standardized = [scale(vector, means, stds) for vector in vectors]
    k = min(DEFAULT_K, len(standardized))
    assignments, centroids, inertia, sizes, iterations = kmeans(
        standardized,
        k,
        random.Random(RANDOM_SEED),
    )
    centroids_original = [inverse_scale(centroid, means, stds) for centroid in centroids]
    labels = assign_labels(centroids_original)

    metrics = {
        "k": k,
        "inertia": round(inertia, 6),
        "cluster_sizes": sizes,
        "iterations": iterations,
        "sample_count": len(rows),
        "random_seed": RANDOM_SEED,
    }

    model = {
        "version": "customer-segment-v1",
        "algorithm": "k-means",
        "trained_at": datetime.now(timezone.utc).isoformat(),
        "features": FEATURES,
        "means": means,
        "stds": stds,
        "centroids": centroids,
        "centroids_original": centroids_original,
        "cluster_labels": labels,
        "k": k,
        "metrics": metrics,
    }

    with open(model_path, "w", encoding="utf-8") as handle:
        json.dump(model, handle, ensure_ascii=False, indent=2)
    with open(metrics_path, "w", encoding="utf-8") as handle:
        json.dump(metrics, handle, ensure_ascii=False, indent=2)

    return metrics


def predict(model_path: str, input_path: str, output_path: str) -> int:
    with open(model_path, encoding="utf-8") as handle:
        model = json.load(handle)

    _, rows = read_csv(input_path)
    means = model["means"]
    stds = model["stds"]
    centroids = model["centroids"]
    labels = model["cluster_labels"]

    predictions = []

    for row in rows:
        vector = [to_float(row.get(feature)) for feature in FEATURES]
        standardized = scale(vector, means, stds)
        cluster = nearest(standardized, centroids)
        predictions.append(
            {
                "sample_id": row.get(ID_COLUMN, ""),
                "cluster": cluster,
                "segment": labels[cluster],
                "value_score": f"{value_score(standardized):.4f}",
                "recency_days": f"{vector[0]:.0f}",
                "frequency": f"{vector[1]:.0f}",
                "monetary": f"{vector[2]:.2f}",
            }
        )

    with open(output_path, "w", newline="", encoding="utf-8") as handle:
        writer = csv.DictWriter(
            handle,
            fieldnames=["sample_id", "cluster", "segment", "value_score", "recency_days", "frequency", "monetary"],
        )
        writer.writeheader()
        writer.writerows(predictions)

    return len(predictions)


def main() -> int:
    parser = argparse.ArgumentParser(description="Segmentation clients VolyLegume")
    subparsers = parser.add_subparsers(dest="command", required=True)

    train_parser = subparsers.add_parser("train")
    train_parser.add_argument("--input", required=True)
    train_parser.add_argument("--model-out", required=True)
    train_parser.add_argument("--metrics-out", required=True)

    predict_parser = subparsers.add_parser("predict")
    predict_parser.add_argument("--model", required=True)
    predict_parser.add_argument("--input", required=True)
    predict_parser.add_argument("--output", required=True)

    args = parser.parse_args()

    if args.command == "train":
        metrics = train(args.input, args.model_out, args.metrics_out)
        print(json.dumps({"status": "trained", "metrics": metrics}, ensure_ascii=False))
        return 0

    if args.command == "predict":
        count = predict(args.model, args.input, args.output)
        print(json.dumps({"status": "predicted", "count": count}, ensure_ascii=False))
        return 0

    return 1


if __name__ == "__main__":
    sys.exit(main())
