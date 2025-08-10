import face_recognition
import json
import sys

def main(image_path):
    # Load uploaded image
    unknown_image = face_recognition.load_image_file(image_path)
    unknown_encoding = face_recognition.face_encodings(unknown_image)
    
    if not unknown_encoding:
        return []
    
    # Compare with database (mock - implement DB connection)
    matches = [
        {"vector_id": 1, "confidence": 0.92},
        {"vector_id": 2, "confidence": 0.87}
    ]
    
    return matches

if __name__ == "__main__":
    result = main(sys.argv[1])
    print(json.dumps(result))