#!/usr/bin/env python3
"""Modele de prevision du rendement (kg/m2) - regression ridge.

Implementation en bibliotheque standard uniquement (pas de numpy/scikit-learn)
afin de fonctionner partout, y compris en CI et dans un conteneur sans Python
scientifique. La methodologie suit le cours : baseline, split train/test,
normalisation, regularisation et metriques (MAE, RMSE, R2).

Usage :
  python3 ml/yield_model.py train --input dataset.csv --model-out model.json --metrics-out metrics.json
  python3 ml/yield_model.py predict --model model.json --input features.csv --output predictions.csv
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
TARGET_COLUMN = "target"
CATEGORICAL = [
    "crop_category",
    "soil_type",
    "irrigation_type",
    "sun_exposure",
    "slope",
    "production_unit_type",
]
UNKNOWN_CATEGORY = "inconnu"
RANDOM_SEED = 42
TEST_RATIO = 0.2
RIDGE_LAMBDA = 1.0


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
    means: dict[str, float] = {}
    stds: dict[str, float] = {}

    for column in features:
        if column in CATEGORICAL:
            values = {(row.get(column) or UNKNOWN_CATEGORY).strip() or UNKNOWN_CATEGORY for row in rows}
            categories[column] = sorted(values)
        else:
            numeric.append(column)
            values = [to_float(row.get(column)) for row in rows]
            mean = sum(values) / len(values) if values else 0.0
            variance = sum((value - mean) ** 2 for value in values) / len(values) if values else 0.0
            means[column] = mean
            stds[column] = math.sqrt(variance) or 1.0

    return categories, numeric, means, stds


def encode_row(row, features, categories, numeric, means, stds) -> list[float]:
    vector = [1.0]
    for column in features:
        if column in CATEGORICAL:
            value = (row.get(column) or UNKNOWN_CATEGORY).strip() or UNKNOWN_CATEGORY
            for category in categories.get(column, []):
                vector.append(1.0 if value == category else 0.0)
        else:
            vector.append((to_float(row.get(column)) - means.get(column, 0.0)) / stds.get(column, 1.0))
    return vector


def solve_linear_system(matrix: list[list[float]], vector: list[float]) -> list[float]:
    size = len(matrix)
    augmented = [row[:] + [vector[index]] for index, row in enumerate(matrix)]

    for column in range(size):
        pivot = max(range(column, size), key=lambda row: abs(augmented[row][column]))
        if abs(augmented[pivot][column]) < 1e-12:
            continue
        augmented[column], augmented[pivot] = augmented[pivot], augmented[column]
        divisor = augmented[column][column]
        for index in range(column, size + 1):
            augmented[column][index] /= divisor
        for row in range(size):
            if row == column:
                continue
            factor = augmented[row][column]
            if factor == 0:
                continue
            for index in range(column, size + 1):
                augmented[row][index] -= factor * augmented[column][index]

    return [augmented[index][size] for index in range(size)]


def fit_ridge(matrix: list[list[float]], targets: list[float], lam: float = RIDGE_LAMBDA) -> list[float]:
    count = len(matrix)
    width = len(matrix[0]) if count else 0
    gram = [[0.0] * width for _ in range(width)]
    moment = [0.0] * width

    for row, target in zip(matrix, targets):
        for i in range(width):
            moment[i] += row[i] * target
            for j in range(width):
                gram[i][j] += row[i] * row[j]

    for index in range(1, width):
        gram[index][index] += lam

    return solve_linear_system(gram, moment)


def predict_value(coefficients: list[float], vector: list[float]) -> float:
    return sum(weight * value for weight, value in zip(coefficients, vector))


def compute_metrics(actual: list[float], predicted: list[float], baseline: float) -> dict[str, float]:
    count = len(actual)
    mae = sum(abs(a - p) for a, p in zip(actual, predicted)) / count
    rmse = math.sqrt(sum((a - p) ** 2 for a, p in zip(actual, predicted)) / count)
    baseline_mae = sum(abs(a - baseline) for a in actual) / count
    mean = sum(actual) / count
    total_variance = sum((a - mean) ** 2 for a in actual)
    residual = sum((a - p) ** 2 for a, p in zip(actual, predicted))
    r2 = 1.0 - (residual / total_variance) if total_variance > 0 else 0.0

    return {
        "mae": mae,
        "rmse": rmse,
        "r2": r2,
        "baseline_mae": baseline_mae,
    }


def train(input_path: str, model_path: str, metrics_path: str) -> dict:
    fieldnames, rows = read_csv(input_path)
    features = feature_columns(fieldnames)
    usable = [row for row in rows if to_float(row.get(TARGET_COLUMN), -1.0) >= 0.0 and (row.get(TARGET_COLUMN) or "").strip() != ""]

    if len(usable) < 3:
        raise SystemExit(f"Jeu de donnees insuffisant : {len(usable)} ligne(s), 3 minimum.")

    indices = list(range(len(usable)))
    random.Random(RANDOM_SEED).shuffle(indices)
    test_size = max(1, int(round(len(usable) * TEST_RATIO)))
    if len(usable) - test_size < 2:
        test_size = max(1, len(usable) - 2)
    test_indices = set(indices[:test_size])
    train_rows = [row for index, row in enumerate(usable) if index not in test_indices]
    test_rows = [row for index, row in enumerate(usable) if index in test_indices]

    categories, numeric, means, stds = fit_encoder(train_rows, features)

    train_matrix = [encode_row(row, features, categories, numeric, means, stds) for row in train_rows]
    train_targets = [to_float(row.get(TARGET_COLUMN)) for row in train_rows]
    coefficients = fit_ridge(train_matrix, train_targets)

    test_matrix = [encode_row(row, features, categories, numeric, means, stds) for row in test_rows]
    test_targets = [to_float(row.get(TARGET_COLUMN)) for row in test_rows]
    test_predictions = [predict_value(coefficients, vector) for vector in test_matrix]

    baseline = sum(train_targets) / len(train_targets)
    metrics = compute_metrics(test_targets, test_predictions, baseline)
    metrics["train_count"] = len(train_rows)
    metrics["test_count"] = len(test_rows)

    model = {
        "version": 1,
        "algorithm": "ridge",
        "trained_at": datetime.now(timezone.utc).isoformat(),
        "features": features,
        "categorical": [name for name in features if name in CATEGORICAL],
        "numeric": numeric,
        "categories": categories,
        "means": means,
        "stds": stds,
        "coefficients": coefficients,
        "target": TARGET_COLUMN,
        "metrics": metrics,
        "sample_count": len(usable),
        "random_seed": RANDOM_SEED,
        "lambda": RIDGE_LAMBDA,
    }

    with open(model_path, "w", encoding="utf-8") as handle:
        json.dump(model, handle, ensure_ascii=False, indent=2)
    with open(metrics_path, "w", encoding="utf-8") as handle:
        json.dump(metrics, handle, ensure_ascii=False, indent=2)

    return metrics


def predict(model_path: str, input_path: str, output_path: str) -> int:
    with open(model_path, encoding="utf-8") as handle:
        model = json.load(handle)

    fieldnames, rows = read_csv(input_path)
    features = model["features"]
    categories = model["categories"]
    numeric = model["numeric"]
    means = model["means"]
    stds = model["stds"]
    coefficients = model["coefficients"]
    r2 = model.get("metrics", {}).get("r2")
    score = max(0.0, min(1.0, float(r2))) if r2 is not None else None

    predictions = []
    for row in rows:
        vector = encode_row(row, features, categories, numeric, means, stds)
        value = max(0.0, predict_value(coefficients, vector))
        predictions.append(
            {
                "sample_id": row.get(ID_COLUMN, ""),
                "prediction": f"{value:.6f}",
                "score": "" if score is None else f"{score:.4f}",
            }
        )

    with open(output_path, "w", newline="", encoding="utf-8") as handle:
        writer = csv.DictWriter(handle, fieldnames=["sample_id", "prediction", "score"])
        writer.writeheader()
        writer.writerows(predictions)

    return len(predictions)


def main() -> int:
    parser = argparse.ArgumentParser(description="Modele de rendement VolyLegume")
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
