export interface CourseGroup {
  label: string
  options: string[]
}

export interface CaviteLocationGroup {
  label: string
  options: string[]
}

export const openToAllCoursesLabel = 'Any / Open to all courses'

export const courseGroups: CourseGroup[] = [
  {
    label: 'Information Technology',
    options: [
      'BS Information Technology',
      'BS Computer Science',
      'BS Information Systems',
      'BS Entertainment and Multimedia Computing',
      'BS Data Science',
    ],
  },
  {
    label: 'Business and Management',
    options: [
      'BS Accountancy',
      'BS Accounting Information System',
      'BS Business Administration',
      'BS Entrepreneurship',
      'BS Office Administration',
      'BS Marketing Management',
      'BS Human Resource Management',
    ],
  },
  {
    label: 'Engineering and Technology',
    options: [
      'BS Civil Engineering',
      'BS Computer Engineering',
      'BS Electrical Engineering',
      'BS Electronics Engineering',
      'BS Industrial Engineering',
      'BS Mechanical Engineering',
      'BS Architecture',
    ],
  },
  {
    label: 'Health and Sciences',
    options: [
      'BS Nursing',
      'BS Medical Technology',
      'BS Pharmacy',
      'BS Psychology',
      'BS Biology',
      'BS Nutrition and Dietetics',
    ],
  },
  {
    label: 'Hospitality and Tourism',
    options: [
      'BS Hospitality Management',
      'BS Tourism Management',
      'BS Culinary Management',
    ],
  },
  {
    label: 'Education and Social Sciences',
    options: [
      'Bachelor of Elementary Education',
      'Bachelor of Secondary Education',
      'BS Social Work',
      'AB Communication',
      'AB Psychology',
      'AB English Language Studies',
    ],
  },
]

export const caviteLocationGroups: CaviteLocationGroup[] = [
  {
    label: 'Cities',
    options: [
      'Bacoor City',
      'Cavite City',
      'Dasmarinas City',
      'General Trias City',
      'Imus City',
      'Tagaytay City',
      'Trece Martires City',
    ],
  },
  {
    label: 'Municipalities',
    options: [
      'Alfonso',
      'Amadeo',
      'Carmona',
      'General Emilio Aguinaldo',
      'General Mariano Alvarez',
      'Indang',
      'Kawit',
      'Magallanes',
      'Maragondon',
      'Mendez',
      'Naic',
      'Noveleta',
      'Rosario',
      'Silang',
      'Tanza',
      'Ternate',
    ],
  },
]

export const allCourseOptions = courseGroups.flatMap((group) => group.options)
export const allCaviteLocations = caviteLocationGroups.flatMap((group) => group.options)

export const caviteBarangaysByLocation: Record<string, string[]> = {
  'Bacoor City': ['Alima', 'Aniban I', 'Aniban II', 'Aniban III', 'Aniban IV', 'Banalo', 'Bayanan', 'Habay I', 'Habay II', 'Kaingen', 'Mambog I', 'Mambog II', 'Mambog III', 'Mambog IV', 'Mambog V', 'Molino I', 'Molino II', 'Molino III', 'Molino IV', 'Molino V', 'Molino VI', 'Molino VII', 'Niog I', 'Niog II', 'Niog III', 'Panapaan I', 'Panapaan II', 'Panapaan III', 'Panapaan IV', 'Panapaan V', 'Panapaan VI', 'Panapaan VII', 'P.F. Espiritu I', 'P.F. Espiritu II', 'P.F. Espiritu III', 'P.F. Espiritu IV', 'P.F. Espiritu V', 'P.F. Espiritu VI', 'P.F. Espiritu VII', 'P.F. Espiritu VIII', 'Real I', 'Real II', 'Salinas I', 'Salinas II', 'Salinas III', 'Salinas IV', 'Sineguelasan', 'Tabing Dagat', 'Talaba I', 'Talaba II', 'Talaba III', 'Talaba IV', 'Talaba V', 'Talaba VI', 'Talaba VII', 'Zapote I', 'Zapote II', 'Zapote III', 'Zapote IV', 'Zapote V'],
  'Cavite City': ['Barangay 1', 'Barangay 2', 'Barangay 3', 'Barangay 4', 'Barangay 5', 'Barangay 7', 'Barangay 8', 'Barangay 9', 'Barangay 10', 'Barangay 11', 'Barangay 12', 'Barangay 14', 'Barangay 15', 'Barangay 16', 'Barangay 17', 'Barangay 18', 'Barangay 19', 'Barangay 20', 'Barangay 21', 'Barangay 22', 'Barangay 23', 'Barangay 24', 'Barangay 25', 'Barangay 26', 'Barangay 27', 'Barangay 28', 'Barangay 29', 'Barangay 30', 'Barangay 31', 'Barangay 32', 'Barangay 33', 'Barangay 34', 'Barangay 35', 'Barangay 36', 'Barangay 37', 'Barangay 38', 'Barangay 39', 'Barangay 40', 'Barangay 41', 'Barangay 42', 'Barangay 43', 'Barangay 44', 'Barangay 45', 'Barangay 46', 'Barangay 47', 'Barangay 48'],
  'Dasmarinas City': ['Burol', 'Burol I', 'Burol II', 'Burol III', 'Datu Esmael', 'Emmanuel Bergado I', 'Emmanuel Bergado II', 'Fatima I', 'Fatima II', 'Fatima III', 'H-2', 'Langkaan I', 'Langkaan II', 'Luzviminda I', 'Luzviminda II', 'Paliparan I', 'Paliparan II', 'Paliparan III', 'Sabang', 'Salawag', 'Salitran I', 'Salitran II', 'Salitran III', 'Salitran IV', 'Sampaloc I', 'Sampaloc II', 'Sampaloc III', 'Sampaloc IV', 'Santiago', 'San Agustin I', 'San Agustin II', 'San Andres I', 'San Andres II', 'San Antonio De Padua I', 'San Antonio De Padua II', 'San Dionisio', 'San Esteban', 'San Francisco I', 'San Francisco II', 'San Isidro Labrador I', 'San Isidro Labrador II', 'San Jose', 'San Lorenzo Ruiz I', 'San Lorenzo Ruiz II', 'San Luis I', 'San Luis II', 'San Manuel I', 'San Manuel II', 'San Mateo', 'San Miguel', 'San Nicolas I', 'San Nicolas II', 'San Roque', 'San Simon', 'Santa Cristina I', 'Santa Cristina II', 'Santa Cruz I', 'Santa Cruz II', 'Santa Fe', 'Santa Lucia', 'Santa Maria', 'Santo Cristo', 'Santo Nino I', 'Santo Nino II', 'Victoria Reyes', 'Zone I', 'Zone IA', 'Zone II', 'Zone III', 'Zone IV'],
  'General Trias City': ['Bagumbayan', 'Biclatan', 'Buenavista I', 'Buenavista II', 'Buenavista III', 'Corregidor', 'Dulong Bayan', 'Governor Ferrer', 'Javalera', 'Manggahan', 'Navarro', 'Panungyanan', 'Pasong Camachile I', 'Pasong Camachile II', 'Pasong Kawayan I', 'Pasong Kawayan II', 'Pinagtipunan', 'Prinza', 'San Francisco', 'San Gabriel', 'San Juan I', 'San Juan II', 'Santa Clara', 'Santiago', 'Tapia', 'Tejero'],
  'Imus City': ['Alapan I-A', 'Alapan I-B', 'Alapan I-C', 'Alapan II-A', 'Alapan II-B', 'Anabu I-A', 'Anabu I-B', 'Anabu I-C', 'Anabu I-D', 'Anabu I-E', 'Anabu I-F', 'Anabu I-G', 'Anabu II-A', 'Anabu II-B', 'Anabu II-C', 'Anabu II-D', 'Anabu II-E', 'Anabu II-F', 'Bagong Silang', 'Bayan Luma I', 'Bayan Luma II', 'Bayan Luma III', 'Bayan Luma IV', 'Bayan Luma V', 'Bayan Luma VI', 'Bucandala I', 'Bucandala II', 'Bucandala III', 'Bucandala IV', 'Carsadang Bago I', 'Carsadang Bago II', 'Malagasang I-A', 'Malagasang I-B', 'Malagasang I-C', 'Malagasang I-D', 'Malagasang I-E', 'Malagasang I-F', 'Malagasang I-G', 'Malagasang II-A', 'Malagasang II-B', 'Malagasang II-C', 'Malagasang II-D', 'Medicion I-A', 'Medicion I-B', 'Medicion I-C', 'Medicion I-D', 'Medicion II-A', 'Medicion II-B', 'Medicion II-C', 'Medicion II-D', 'Pag-Asa I', 'Pag-Asa II', 'Pag-Asa III', 'Poblacion I-A', 'Poblacion I-B', 'Poblacion I-C', 'Poblacion II-A', 'Poblacion II-B', 'Poblacion III-A', 'Poblacion III-B', 'Poblacion IV-A', 'Poblacion IV-B', 'Poblacion IV-C', 'Poblacion IV-D', 'Tanzang Luma I', 'Tanzang Luma II', 'Tanzang Luma III', 'Tanzang Luma IV'],
  'Tagaytay City': ['Asisan', 'Bagong Tubig', 'Calabuso', 'Dapdap East', 'Dapdap West', 'Francisco', 'Guinhawa North', 'Guinhawa South', 'Iruhin Central', 'Iruhin East', 'Iruhin South', 'Kaybagal Central', 'Kaybagal North', 'Mag-Asawang Ilat', 'Maharlika East', 'Maharlika West', 'Maitim 2nd Central', 'Maitim 2nd East', 'Maitim 2nd West', 'Mendez Crossing East', 'Mendez Crossing West', 'Neogan', 'Patutong Malaki North', 'Patutong Malaki South', 'Sambong', 'San Jose', 'Silang Junction North', 'Silang Junction South', 'Sungay East', 'Sungay West', 'Tolentino East', 'Tolentino West', 'Zambal'],
  'Trece Martires City': ['Aguado', 'Cabezas', 'Cabuco', 'Conchu', 'De Ocampo', 'Gregorio', 'Hugo Perez', 'Inocencio', 'Lapidario', 'Lallana', 'Luciano', 'Osorio', 'Perez', 'San Agustin', 'De Ocampo'],
  Alfonso: ['Amuyong', 'Buck Estate', 'Esperanza Ibaba', 'Esperanza Ilaya', 'Kaytitinga I', 'Kaytitinga II', 'Kaytitinga III', 'Luksuhin', 'Mangas I', 'Mangas II', 'Marahan I', 'Marahan II', 'Matagbak I', 'Matagbak II', 'Palumlum', 'Sinaliw Malaki', 'Sinaliw na Malaki', 'Sinaliw na Munti', 'Upli'],
  Amadeo: ['Banaybanay', 'Barangay I', 'Barangay II', 'Barangay III', 'Barangay IV', 'Barangay V', 'Barangay VI', 'Barangay VII', 'Barangay VIII', 'Bucal', 'Halang', 'Maymangga', 'Minantok Kanluran', 'Minantok Silangan', 'Pangil', 'Salaban', 'Talon', 'Tamacan'],
  Carmona: ['Bancal', 'Barangay 1', 'Barangay 2', 'Barangay 3', 'Barangay 4', 'Barangay 5', 'Barangay 6', 'Barangay 7', 'Barangay 8', 'Cabilang Baybay', 'Lantic', 'Mabuhay', 'Maduya', 'Milagrosa'],
  'General Emilio Aguinaldo': ['A. Dalusag', 'Batas', 'Castanos Cerca', 'Castanos Lejos', 'Kabulusan', 'Kaymisas', 'Kaypaaba', 'Mabolo', 'Poblacion', 'Tabora'],
  'General Mariano Alvarez': ['Aldiano Olaes', 'Bernardo Pulido', 'Epifanio Malia', 'Francisco Reyes', 'Gavino Maderan', 'Jacinto Lumbreras', 'Kapitan Kua', 'Koronel Jose P. Elises', 'Macario Dacon', 'Marcelino Memije', 'Nicolasa Virata', 'Pantaleon Granados', 'Ramon Cruz', 'San Gabriel', 'Severino de las Alas', 'Tiniente Tiago'],
  Indang: ['Alulod', 'Banaba Cerca', 'Banaba Lejos', 'Bancod', 'Barangay 1', 'Barangay 2', 'Barangay 3', 'Barangay 4', 'Buna Cerca', 'Buna Lejos I', 'Buna Lejos II', 'Calumpang Cerca', 'Calumpang Lejos I', 'Calumpang Lejos II', 'Kayquit I', 'Kayquit II', 'Kayquit III', 'Lumampong Balagbag', 'Lumampong Halayhay', 'Mahabangkahoy Cerca', 'Mahabangkahoy Lejos', 'Mataas na Lupa', 'Pulo', 'Tambo Balagbag', 'Tambo Kulit', 'Tambo Malaki'],
  Kawit: ['Binakayan-Aplaya', 'Binakayan-Kanluran', 'Congbalay-Legaspi', 'Kaingen', 'Magdalo', 'Marulas', 'Panamitan', 'Pulvorista', 'Samala-Marquez', 'San Sebastian', 'Santa Isabel', 'Tabon I', 'Tabon II', 'Tabon III', 'Tramo-Bantayan', 'Wakas I', 'Wakas II'],
  Magallanes: ['Baliwag', 'Bendita I', 'Bendita II', 'Caluangan', 'Medina', 'Pacheco', 'Ramirez', 'Tua'],
  Maragondon: ['Bucal I', 'Bucal II', 'Bucal III A', 'Bucal III B', 'Bucal IV A', 'Bucal IV B', 'Caingin Poblacion', 'Conchu I', 'Conchu II', 'Garita I A', 'Garita I B', 'Garita II A', 'Garita II B', 'Layong Mabilog', 'Mabato', 'Pantihan I', 'Pantihan II', 'Pantihan III', 'Pantihan IV', 'Patungan', 'Pinagsanhan I A', 'Pinagsanhan I B', 'Pinagsanhan II', 'San Miguel I', 'San Miguel II'],
  Mendez: ['Anuling Cerca I', 'Anuling Cerca II', 'Anuling Lejos I', 'Anuling Lejos II', 'Asin', 'Bukal', 'Galicia I', 'Galicia II', 'Miguel Mojica', 'Palocpoc I', 'Palocpoc II', 'Panungyanan', 'Poblacion I', 'Poblacion II', 'Poblacion III', 'Poblacion IV', 'Poblacion V', 'Poblacion VI', 'Poblacion VII', 'Poblacion VIII', 'Poblacion IX'],
  Naic: ['Bagong Karsada', 'Balsahan', 'Bancaan', 'Bucana Malaki', 'Bucana Sasahan', 'Calubcob', 'Capt. C. Nazareno', 'Gomez-Zamora', 'Halang', 'Ibayo Estacion', 'Labac', 'Latoria', 'Mabolo', 'Makina', 'Malainen Bago', 'Malainen Luma', 'Molino', 'Munting Mapino', 'Muzon', 'Palangue I', 'Palangue II', 'Sabang', 'San Roque', 'Santulan', 'Sapa', 'Timalan Balsahan', 'Timalan Concepcion'],
  Noveleta: ['Magdiwang', 'Poblacion', 'Salcedo I', 'Salcedo II', 'Salcedo III', 'Salcedo IV', 'San Antonio I', 'San Antonio II', 'San Jose I', 'San Jose II', 'San Rafael I', 'San Rafael II', 'San Rafael III'],
  Rosario: ['Bagbag I', 'Bagbag II', 'Kanluran', 'Ligtong I', 'Ligtong II', 'Ligtong III', 'Ligtong IV', 'Muzon I', 'Muzon II', 'Muzon III', 'Muzon IV', 'Poblacion', 'Sapa I', 'Sapa II', 'Silangan', 'Wawa I', 'Wawa II', 'Wawa III'],
  Silang: ['Adlas', 'Anahaw I', 'Anahaw II', 'Balite I', 'Balite II', 'Banaba', 'Biga I', 'Biga II', 'Biluso', 'Bucal', 'Bulihan', 'Cabangaan', 'Carmen', 'Hoyo', 'Hukay', 'Inchican', 'Ipil I', 'Ipil II', 'Kalubkob', 'Kaong', 'Lalaan I', 'Lalaan II', 'Lucsuhin', 'Lumil', 'Maguyam', 'Maitim I', 'Maitim II', 'Narra I', 'Narvaez', 'Paligawan', 'Pasong Langka', 'Poblacion I', 'Poblacion II', 'Poblacion III', 'Poblacion IV', 'Poblacion V', 'Poblacion VI', 'Pulong Bunga', 'Pulong Saging', 'Puting Kahoy', 'Sabutan', 'San Miguel I', 'San Miguel II', 'Santol', 'Tartaria', 'Tibig'],
  Tanza: ['Amaya I', 'Amaya II', 'Amaya III', 'Amaya IV', 'Amaya V', 'Amaya VI', 'Amaya VII', 'Bagtas', 'Biga', 'Biwas', 'Bucal', 'Calibuyo', 'Capipisa', 'Daang Amaya I', 'Daang Amaya II', 'Daang Amaya III', 'Julugan I', 'Julugan II', 'Julugan III', 'Julugan IV', 'Julugan V', 'Lambingan', 'Mulawin', 'Paradahan I', 'Paradahan II', 'Punta I', 'Punta II', 'Sahud Ulan', 'Sanja Mayor', 'Santol', 'Tanauan', 'Tres Cruses'],
  Ternate: ['Bucana', 'Calumpang', 'Julugan I', 'Julugan II', 'Julugan III', 'Lumipa', 'Poblacion I', 'Poblacion II', 'Poblacion III', 'San Jose', 'Sapang I', 'Sapang II'],
}
