# Función para solicitar datos por teclado y limpiarlos
solicitar_vector <- function() {
  cat("Por favor, escriba los números separados por espacios y presione Enter.\n")
  cat("Ejemplo: 12 15 18 20 22\n")
  
  # Capturar la línea de texto escrita por el usuario
  data_input <- readline(prompt = "-> ")
  # Convertir la cadena de texto que queda en data_input en un vector 
  vector_data <- strsplit(data_input, split = ",")[[1]]
  
  # Convertir el vector de caracteres a vector numérico
  vector_num <- as.numeric(vector_data)
  
  # Verificar si hubo errores en la conversión (ej. si el usuario escribió letras)
  if (any(is.na(vector_num))) {
    stop("Error: Ingresó valores no numéricos. Por favor reinicie y escriba solo números.")
  }
  
  # Verificar que haya suficientes datos
  if (length(vector_num) < 2) {
    stop("Error: Se requiere un mínimo de 2 números para calcular cuartiles.")
  }
  
  cat("Datos ingresados correctamente.\n")
  return(vector_num)
}

calcular_cuartiles_logica <- function(vector_datos) {
  data_ordered <- sort(vector_datos)
  n <- length(data_ordered) #capturar tamaño vector
  
  calcular_cuartil <- function(k, data_ordered, n) {
    indice_calculado <- (k * (n + 1)) / 4
    parte_entera <- floor(indice_calculado)
    d <- indice_calculado - parte_entera
    
    if (d == 0) {
      resultado <- data_ordered[indice_calculado]
    } else {
      valor_bajo <- data_ordered[parte_entera]
      valor_alto <- data_ordered[parte_entera + 1]
      resultado <- valor_bajo + d * (valor_alto - valor_bajo)
    }
    return(resultado)
  }
  
  Q1 <- calcular_cuartil(1, data_ordered, n)
  Q2 <- calcular_cuartil(2, data_ordered, n)
  Q3 <- calcular_cuartil(3, data_ordered, n)
  
  cat("--- Resultados del Cálculo ---\n")
  cat(paste("Datos ordenados:", paste(data_ordered, collapse = ", "), "\n"))
  cat(paste("Número de elementos (n):", n, "\n\n"))
  cat(sprintf("Q1 (Primer Cuartil):                 %.1f\n", Q1))
  cat(sprintf("Q2 (Segundo Cuartil - Mediana):       %.1f\n", Q2))
  cat(sprintf("Q3 (Tercer Cuartil):                 %.1f\n", Q3))
}

# 1. Solicitar datos al usuario
mis_datos <- solicitar_vector()

# 2. Calcular y mostrar resultados
if (!is.null(mis_datos)) {
  calcular_cuartiles_logica(mis_datos)
}

