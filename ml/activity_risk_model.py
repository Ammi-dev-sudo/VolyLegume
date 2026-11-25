#!/usr/bin/env python3
"""Modele de risque de retard d'une activite agricole - regression logistique.

Implementation en bibliotheque standard uniquement (pas de numpy/scikit-learn).
La cible vaut 1 lorsqu'une activite realisee l'a ete apres sa date prevue, ou
lorsqu'une activite planifiee est deja en retard. Les variables sont connues au
moment de la planification (type, culture, delais, charge de la semaine).

Usage :
  python3 ml/activity_risk_model.py train --input dataset.csv --model-out model.json --metrics-out metrics.json
  python3 ml/activity_risk_model.py predict --model model.json --input features.csv --output predictions.csv
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
TARGET_COLUMN = "late"
CATEGORICAL = ["activity_type", "crop_category"]
UNKNOWN_CATEGORY = "inconnu"
RANDOM_SEED = 42
TEST_RATIO = 0.2
L2_PENALTY = 1.0
LEARNING_RATE = 0.15
EPOCHS = 800
THRESHOLD = 0.5


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


def feature_columns(fieldnames: list[str]) -> list[str]:
    return [name for name in fieldnames if name not in (ID_COLUMN, TARGET_COLUMN)]


def fit_encoder(rows: list[dict[str, str]], features: list[str]):
    categories: dict[str, list[str]] = {}
    numeric: list[str] = []

    for column in features:
        if column in CATEGORICAL:
            values = {(row.get(column) or UNKNOWN_CATEGORY).strip() or UNKNOWN_CATEGORY for row in rows}
            values.add(UNKNOWN_CATEGORY)
            categories[column] = sorted(values)
        else:
            numeric.append(column)

    return categories, numeric


def raw_vector(row, features, categories, numeric) -> list[float]:
    vector: list[float] = []

    for column in features:
        if column in CATEGORICAL:
            value = (row.get(column) or UNKNOWN_CATEGORY).strip() or UNKNOWN_CATEGORY
            for category in categories.get(column, []):
                vector.append(1.0 if value == category else 0.0)
        else:
            vector.append(to_float(row.get(column)))

    return vector


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


def sigmoid(value: float) -> float:
    if value >= 0:
        return 1.0 / (1.0 + math.exp(-value))

    exponential = math.exp(value)

    return exponential / (1.0 + exponential)


def stratified_split(rows: list[dict[str, str]], ratio: float, seed: int):
    rng = random.Random(seed)
    positives = [row for row in rows if int(to_float(row.get(TARGET_COLUMN))) == 1]
    negatives = [row for row in rows if int(to_float(row.get(TARGET_COLUMN))) == 0]
    rng.shuffle(positives)
    rng.shuffle(negatives)

    def split(group: list[dict[str, str]]):
        if len(group) <= 1:
            return group, []
        test_size = max(1, int(round(len(group) * ratio)))
        test_size = min(test_size, len(group) - 1)
        return group[test_size:], group[:test_size]

    train_positives, test_positives = split(positives)
    train_negatives, test_negatives = split(negatives)
    train_rows = train_positives + train_negatives
    test_rows = test_positives + test_negatives
    rng.shuffle(train_rows)
    rng.shuffle(test_rows)

    return train_rows, test_rows


def fit_logistic(matrix: list[list[float]], targets: list[float]) -> tuple[list[float], float]:
    count = len(matrix)
    width = len(matrix[0]) if matrix else 0
    weights = [0.0] * width
    bias = 0.0
    positives = sum(1 for target in targets if target == 1)
    negatives = count - positives
    positive_weight = negatives / positives if positives > 0 and negatives > 0 else 1.0
    sample_weights = [positive_weight if target == 1 else 1.0 for target in targets]
    total_weight = sum(sample_weights) or 1.0

    for _ in range(EPOCHS):
        gradient_weights = [0.0] * width
        gradient_bias = 0.0

        for row, target, sample_weight in zip(matrix, targets, sample_weights):
            probability = sigmoid(bias + sum(weight * value for weight, value in zip(weights, row)))
            error = (probability - target) * sample_weight

            for index in range(width):
                gradient_weights[index] += error * row[index]

            gradient_bias += error

        for index in range(width):
            weights[index] -= LEARNING_RATE * (gradient_weights[index] / total_weight + L2_PENALTY * weights[index] / total_weight)

        bias -= LEARNING_RATE * gradient_bias / total_weight

    return weights, bias


def probability(weights: list[float], bias: float, vector: list[float]) -> float:
    return sigmoid(bias + sum(weight * value for weight, value in zip(weights, vector)))


def compute_auc(targets: list[int], probabilities: list[float]) -> float:
    positives = [p for target, p in zip(targets, probabilities) if target == 1]
    negatives = [p for target, p in zip(targets, probabilities) if target == 0]

    if not positives or not negatives:
        return 0.5

    wins = 0.0

    for positive in positives:
        for negative in negatives:
            if positive > negative:
                wins += 1.0
            elif positive == negative:
                wins += 0.5

    return wins / (len(positives) * len(negatives))


def compute_metrics(targets: list[int], probabilities: list[float]) -> dict[str, float]:
    count = len(targets)

    if count == 0:
        return {
            "accuracy": 0.0,
            "precision": 0.0,
            "recall": 0.0,
            "f1": 0.0,
            "auc": 0.5,
            "log_loss": 0.0,
            "base_rate": 0.0,
        }

    predictions = [1 if p >= THRESHOLD else 0 for p in probabilities]
    true_positive = sum(1 for target, prediction in zip(targets, predictions) if target == 1 and prediction == 1)
    true_negative = sum(1 for target, prediction in zip(targets, predictions) if target == 0 and prediction == 0)
    false_positive = sum(1 for target, prediction in zip(targets, predictions) if target == 0 and prediction == 1)
    false_negative = sum(1 for target, prediction in zip(targets, predictions) if target == 1 and prediction == 0)

    precision = true_positive / (true_positive + false_positive) if true_positive + false_positive else 0.0
    recall = true_positive / (true_positive + false_negative) if true_positive + false_negative else 0.0
    f1 = 2 * precision * recall / (precision + recall) if precision + recall else 0.0
    log_loss = -sum(
        target * math.log(max(p, 1e-12)) + (1 - target) * math.log(max(1 - p, 1e-12))
        for target, p in zip(targets, probabilities)
    ) / count

    return {
        "accuracy": (true_positive + true_negative) / count,
        "precision": precision,
        "recall": recall,
        "f1": f1,
        "auc": compute_auc(targets, probabilities),
        "log_loss": log_loss,
        "base_rate": sum(targets) / count,
    }


def train(input_path: str, model_path: str, metrics_path: str) -> dict:
    fieldnames, rows = read_csv(input_path)
    features = feature_columns(fieldnames)
    usable = [
        row
        for row in rows
        if (row.get(TARGET_COLUMN) or "").strip() != "" and int(to_float(row.get(TARGET_COLUMN), -1.0)) in (0, 1)
    ]

    positives = sum(1 for row in usable if int(to_float(row.get(TARGET_COLUMN))) == 1)
    negatives = len(usable) - positives

    if positives < 2 or negatives < 2:
        raise SystemExit(
            f"Jeu de donnees insuffisant : {positives} activite(s) en retard et "
            f"{negatives} a l'heure (2 minimum de chaque)."
        )

    train_rows, test_rows = stratified_split(usable, TEST_RATIO, RANDOM_SEED)
    categories, numeric = fit_encoder(train_rows, features)
    train_vectors = [raw_vector(row, features, categories, numeric) for row in train_rows]
    means, stds = fit_scaler(train_vectors)
    train_matrix = [scale(vector, means, stds) for vector in train_vectors]
    train_targets = [int(to_float(row.get(TARGET_COLUMN))) for row in train_rows]
    weights, bias = fit_logistic(train_matrix, train_targets)

    test_matrix = [
        scale(raw_vector(row, features, categories, numeric), means, stds) for row in test_rows
    ]
    test_targets = [int(to_float(row.get(TARGET_COLUMN))) for row in test_rows]
    test_probabilities = [probability(weights, bias, vector) for vector in test_matrix]
    metrics = compute_metrics(test_targets, test_probabilities)
    metrics["train_count"] = len(train_rows)
    metrics["test_count"] = len(test_rows)
    metrics["positive_count"] = positives
    metrics["negative_count"] = negatives
    metrics["threshold"] = THRESHOLD

    model = {
        "version": "activity-risk-v1",
        "algorithm": "logistic-regression",
        "trained_at": datetime.now(timezone.utc).isoformat(),
        "features": features,
        "categorical": [name for name in features if name in CATEGORICAL],
        "numeric": numeric,
        "categories": categories,
        "means": means,
        "stds": stds,
        "weights": weights,
        "bias": bias,
        "threshold": THRESHOLD,
        "target": TARGET_COLUMN,
        "metrics": metrics,
        "sample_count": len(usable),
        "random_seed": RANDOM_SEED,
        "l2": L2_PENALTY,
        "learning_rate": LEARNING_RATE,
        "epochs": EPOCHS,
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
    features = model["features"]
    categories = model["categories"]
    numeric = model["numeric"]
    means = model["means"]
    stds = model["stds"]
    weights = model["weights"]
    bias = model["bias"]

    predictions = []

    for row in rows:
        vector = scale(raw_vector(row, features, categories, numeric), means, stds)
        value = probability(weights, bias, vector)
        predictions.append(
            {
                "sample_id": row.get(ID_COLUMN, ""),
                "prediction": f"{value:.6f}",
                "score": f"{value:.6f}",
            }
        )

    with open(output_path, "w", newline="", encoding="utf-8") as handle:
        writer = csv.DictWriter(handle, fieldnames=["sample_id", "prediction", "score"])
        writer.writeheader()
        writer.writerows(predictions)

    return len(predictions)


def main() -> int:
    parser = argparse.ArgumentParser(description="Modele de risque de retard VolyLegume")
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
